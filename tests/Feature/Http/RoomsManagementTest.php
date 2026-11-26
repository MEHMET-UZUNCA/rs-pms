<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class RoomsManagementTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = $this->makeUser('admin');
    }

    private function makeUser(string $role): User
    {
        $user = User::create(['username' => 'U'.$role, 'name' => $role, 'email' => strtolower($role).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', $role)->value('id')]);

        return $user;
    }

    private function roomId(string $number): int
    {
        return (int) DB::table('pms_rooms')->where('number', $number)->value('id');
    }

    private function reason(): int
    {
        return (int) DB::table('pms_reason_codes')->where('category', 'room_status')->where('code', 'OOO')->value('id');
    }

    public function test_out_of_order_tabs_add_and_release(): void
    {
        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza', ['rooms' => [$this->roomId('1120')], 'type' => 'OOO', 'date_from' => 't', 'date_to' => '+3',
            'reason_code_id' => $this->reason(), 'note' => 'AC broken'])->assertSessionHas('status');
        $this->actingAs($this->user)->post('/kat-hizmetleri/ariza', ['rooms' => [$this->roomId('1121')], 'type' => 'OOS', 'date_from' => '+10', 'date_to' => '+12',
            'reason_code_id' => $this->reason()])->assertSessionHas('status');

        $current = $this->actingAs($this->user)->get('/arizali-odalar')->assertOk()->getContent();
        $this->assertStringContainsString('AC broken', $current);
        $this->assertStringContainsString('04.12.2026', $current);           // oda açılış tarihi (girişteki gibi)
        $this->assertStringNotContainsString(route('rooms.history', ['oda' => '1121']), $current);   // planlanan, güncelde yok
        $this->actingAs($this->user)->get('/arizali-odalar?sekme=planned')->assertSee('1121')->assertSee('OOS');
        $this->actingAs($this->user)->get('/arizali-odalar?tur=oos')->assertDontSee('AC broken');

        $id = DB::table('pms_room_outages')->where('room_id', $this->roomId('1120'))->value('id');
        $this->actingAs($this->user)->post("/kat-hizmetleri/ariza/{$id}/kaldir")->assertSessionHas('status');
        $this->actingAs($this->user)->get('/arizali-odalar?sekme=past')->assertSee('AC broken');
        $this->actingAs($this->user)->get('/arizali-odalar')->assertDontSee('AC broken');
    }

    public function test_room_history_shows_guests_status_changes_and_outages(): void
    {
        $this->actingAs($this->user)->post('/kat-hizmetleri/durum', ['rooms' => [$this->roomId('1102')], 'code' => 'INS'])->assertSessionHas('status');
        $html = $this->actingAs($this->user)->get('/oda-gecmisi?oda=1102')->assertOk()->getContent();
        $this->assertStringContainsString('Yılmaz, Ahmet', $html);              // 1102'de kalan misafir
        $this->assertStringContainsString('INSPECTED', $html);                  // durum değişikliği
        $this->assertStringContainsString('Room status changes', $html);
        $this->actingAs($this->user)->get('/oda-gecmisi?oda=9999')->assertOk()->assertSee('Room 9999 not found.');
        $this->actingAs($this->user)->get('/oda-gecmisi')->assertOk()->assertSee('Enter a room number');
    }

    public function test_housekeeping_status_bar(): void
    {
        $html = $this->actingAs($this->user)->get('/kat-hizmetleri')->assertOk()->getContent();
        foreach (['Rms to sell', 'Exp arr', 'Curr occ %', 'Inh ton.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertMatchesRegularExpression('/data-bar="to_sell">155</', $html);
    }

    public function test_permissions_and_menu(): void
    {
        $this->actingAs($this->user)->get('/')->assertSee(route('rooms.out-of-order'), false)->assertSee(route('rooms.history'), false);
        $acc = $this->makeUser('ACC');   // muhasebe: kat hizmetleri yetkisi yok
        $this->actingAs($acc)->get('/arizali-odalar')->assertForbidden();
        $this->actingAs($acc)->get('/oda-gecmisi?oda=1102')->assertOk();   // rezervasyon görme yetkisi var
        $this->actingAs($this->makeUser('IFC'))->get('/oda-gecmisi')->assertForbidden();
    }
}
