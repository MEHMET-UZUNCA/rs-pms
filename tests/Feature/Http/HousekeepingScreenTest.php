<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class HousekeepingScreenTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026; 1107 ve 1106 bugün çıktı (kirli)
        $this->user = User::create(['username' => 'HK', 'name' => 'HK', 'email' => 'hk@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'HKS')->value('id')]);   // Housekeeping Supervisor
    }

    private function room(string $no): object
    {
        return DB::table('pms_rooms')->where('number', $no)->first();
    }

    public function test_board_shows_fidelio_codes_counts_and_guests(): void
    {
        $html = $this->actingAs($this->user)->get('/kat-hizmetleri')->assertOk()->getContent();
        $this->assertSame(155, substr_count($html, 'data-room="'));
        foreach (['INS · Inspected', 'CL · Clean', 'DI · Dirty', 'TU · T.U', 'title="Sleep Out"', 'title="Double Lock"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('Yılmaz, Ahmet', $html);   // 1102 içeride
        $this->assertMatchesRegularExpression('/data-count="vacant_dirty">2</', $html);   // 1106, 1107 (çıkış yaptı)
        $this->assertMatchesRegularExpression('/data-count="occupied_clean">6</', $html);   // girişte kat durumu değişmez

        $vd = $this->actingAs($this->user)->get('/kat-hizmetleri?f=vd')->getContent();
        $this->assertSame(2, substr_count($vd, 'data-room="'));
    }

    public function test_status_and_flags_for_selected_rooms_are_logged(): void
    {
        $ids = [$this->room('1106')->id, $this->room('1107')->id];
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['rooms' => $ids, 'code' => 'CL'])->assertSessionHas('status', '2 rooms updated.');
        $this->assertSame('clean', $this->room('1106')->hk_status);
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['rooms' => [$ids[0]], 'code' => 'INS']);
        $this->assertSame('inspected', $this->room('1106')->hk_status);
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['rooms' => [$this->room('1102')->id], 'code' => 'DND']);
        $this->assertSame('DND', $this->room('1102')->hk_flag);
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['rooms' => [$this->room('1102')->id], 'code' => 'CLEAR']);
        $this->assertNull($this->room('1102')->hk_flag);

        $this->assertSame(['dirty', 'clean'], [DB::table('pms_room_status_logs')->where('room_id', $ids[0])->where('field', 'hk_status')->orderBy('id')->value('from_value'),
            DB::table('pms_room_status_logs')->where('room_id', $ids[0])->where('field', 'hk_status')->orderBy('id')->value('to_value')]);
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['code' => 'CL'])->assertSessionHasErrors('rooms');
    }

    public function test_out_of_order_rules_and_release(): void
    {
        $reason = DB::table('pms_reason_codes')->where('category', 'room_status')->where('code', 'OOO')->value('id');
        $free = $this->room('1111');
        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza', ['rooms' => [$free->id], 'type' => 'OOO', 'date_from' => 't', 'date_to' => '+3', 'reason_code_id' => $reason, 'note' => 'Klima'])
            ->assertSessionHas('status');
        $this->assertSame('out_of_order', $this->room('1111')->service_status);
        $this->assertSame(['2026-12-01', '2026-12-04'], [DB::table('pms_room_outages')->value('date_from'), DB::table('pms_room_outages')->value('date_to')]);
        $this->actingAs($this->user)->get('/kat-hizmetleri?f=ooo')->assertSee('Klima')->assertSee('data-room="1111"', false);

        // İçeride misafiri olan odaya OOO girilemez
        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza', ['rooms' => [$this->room('1102')->id], 'type' => 'OOO', 'date_from' => 't', 'date_to' => '+1', 'reason_code_id' => $reason])
            ->assertSessionHas('error');
        // Aynı odaya çakışan ikinci kayıt girilemez
        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza', ['rooms' => [$free->id], 'type' => 'OOS', 'date_from' => '+1', 'date_to' => '+2', 'reason_code_id' => $reason])
            ->assertSessionHas('error');

        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza/'.DB::table('pms_room_outages')->value('id').'/kaldir')->assertSessionHas('status', 'Room released.');
        $this->assertSame('in_service', $this->room('1111')->service_status);
        $this->assertSame('dirty', $this->room('1111')->hk_status);   // arıza sonrası kirli döner
    }

    public function test_front_office_agent_sees_board_but_cannot_put_ooo(): void
    {
        $agent = User::create(['username' => 'FO', 'name' => 'FO', 'email' => 'fo@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $agent->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($agent)->get('/kat-hizmetleri')->assertOk()->assertDontSee('Put out of order');
        $this->actingAs($agent)->post('/kat-hizmetleri/ariza', ['rooms' => [1], 'type' => 'OOO'])->assertForbidden();
    }

    public function test_phone_screen_lists_dirty_rooms_changes_status_and_reports_issue(): void
    {
        $html = $this->actingAs($this->user)->get('/kat-hizmetleri/mobil')->assertOk()->getContent();
        $this->assertStringContainsString('data-mobile-room="1107"', $html);
        $this->assertStringContainsString('width=device-width', $html);
        $this->assertStringNotContainsString('data-mobile-room="1102"', $html);   // dolu ve temiz oda kirli listesinde yok
        $guest = DB::table('pms_reservations as r')->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->where('r.status', 'in_house')->value('p.last_name');
        $this->assertStringNotContainsString((string) $guest, $this->actingAs($this->user)->get('/kat-hizmetleri/mobil?f=hepsi')->getContent());   // misafir adı yok

        $this->actingAs($this->user)->from('/kat-hizmetleri/mobil')->post('/kat-hizmetleri/durum', ['rooms' => [$this->room('1107')->id], 'code' => 'CL'])
            ->assertRedirect('/kat-hizmetleri/mobil');
        $this->assertSame('clean', $this->room('1107')->hk_status);
        $this->assertStringNotContainsString('data-mobile-room="1107"', $this->actingAs($this->user)->get('/kat-hizmetleri/mobil')->getContent());

        // HKS'in istek yetkisi var: arıza teknik servise iş olarak düşer, telefon ekranına dönülür
        $this->actingAs($this->user)->post('/istekler', ['room' => '1107', 'kind' => 'request', 'department' => 'technical', 'priority' => 'normal',
            'due_time' => '18:00', 'message' => 'Klima damlatıyor', 'return' => 'mobile'])->assertRedirect(route('housekeeping.mobile'));
        $this->assertTrue(DB::table('pms_traces')->where('department', 'technical')->where('message', 'Klima damlatıyor')->exists());
    }
}
