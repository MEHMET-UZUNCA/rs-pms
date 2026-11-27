<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class RoomRackTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ON', 'name' => 'Ön büro', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    public function test_rack_shows_rooms_days_and_reservation_bars(): void
    {
        $html = $this->actingAs($this->user)->get('/oda-plani')->assertOk()->getContent();
        $this->assertStringContainsString('Room Rack', $html);
        $this->assertSame(155, substr_count($html, 'data-room="'));   // Rocks Hotel aktif odaları
        $this->assertStringContainsString('01.12', $html);
        $this->assertStringContainsString('<br>14.12', $html);   // 14 gün
        $this->assertStringNotContainsString('<br>15.12', $html);
        $this->assertStringContainsString('Yılmaz, Ahmet', $html);   // 1102 içeride
        $this->assertStringContainsString('data-kind="in_house"', $html);
        $this->assertStringContainsString('data-kind="confirmed"', $html);   // 1108 Öztürk, bugün gelecek
        $this->assertStringContainsString('rounded-l-none', $html);   // başlangıçtan önce başlayan konaklama
        $this->assertStringContainsString('data-hotkey="ctrl+l"', $html);
    }

    public function test_span_navigation_and_filters(): void
    {
        $this->actingAs($this->user)->get('/oda-plani?gun=7')->assertSee('<br>07.12', false)->assertDontSee('<br>08.12', false);
        $this->actingAs($this->user)->get('/oda-plani?gun=30&bas=%2B7')->assertSee('<br>08.12', false)->assertSee('<br>06.01', false)->assertDontSee('<br>07.12', false);

        $suite = DB::table('pms_room_types')->where('code', 'YSU')->value('id');
        $html = $this->actingAs($this->user)->get("/oda-plani?tip={$suite}")->getContent();
        $this->assertSame(9, substr_count($html, 'data-room="'));
        $this->assertStringContainsString('Johnson, Mark', $html);   // 1121, bugün geliyor (1112 Cohen bugün çıkıyor: çıkış günü planda yok)
        $this->assertStringNotContainsString('Yılmaz, Ahmet', $html);
    }

    public function test_outage_and_daily_summary(): void
    {
        $room = DB::table('pms_rooms')->where('number', '1111')->value('id');
        $reason = DB::table('pms_reason_codes')->insertGetId(['property_id' => $this->property, 'category' => 'outage', 'code' => 'AC', 'name' => 'Klima']);
        DB::table('pms_room_outages')->insert(['property_id' => $this->property, 'room_id' => $room, 'type' => 'OOO', 'date_from' => '2026-12-02',
            'date_to' => '2026-12-05', 'reason_code_id' => $reason, 'note' => 'Klima arızası']);

        $html = $this->actingAs($this->user)->get('/oda-plani')->getContent();
        $this->assertStringContainsString('data-kind="ooo"', $html);
        $this->assertStringContainsString('OOO · Klima arızası', $html);
        // Bu gece: 4 içeride (Cohen ve Smith bugün çıkıyor) + 2 odalı gelen = 6 dolu (155 odanın %4'ü); 2 gelen odasız (Petrov, Levi)
        $this->assertMatchesRegularExpression('/tabular-nums">6<span class="text-muted"> · 4%/', preg_replace('/\s+/', ' ', $html));
        $this->assertMatchesRegularExpression('/text-danger font-semibold">2</', preg_replace('/\s+/', ' ', $html));
    }

    public function test_bar_actions_menu(): void
    {
        $html = $this->actingAs($this->user)->get('/oda-plani')->getContent();
        $yilmaz = DB::table('pms_reservations as r')->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->where('p.last_name', 'Yılmaz')->value('r.id');
        $ozturk = DB::table('pms_reservations as r')->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->where('p.last_name', 'Öztürk')->value('r.id');
        $this->assertStringContainsString('id="rack-menu"', $html);
        $actions = fn (int $id) => json_decode(html_entity_decode(preg_match('/data-bar="'.$id.'"[^>]*data-actions=\'([^\']*)\'/', $html, $m) ? $m[1] : '{}'), true);
        $this->assertSame(['card', 'folio'], array_keys($actions($yilmaz)));             // içeride: kart, folyo
        $this->assertSame(['card', 'checkin'], array_keys($actions($ozturk)));            // bugün gelecek, odası var: giriş
        $this->assertStringContainsString('data-new-url=', $html);
    }

    public function test_drag_moves_room_and_dates_with_rules(): void
    {
        $res = fn (string $last) => \App\Domain\Reservations\Models\Reservation::whereHas('profile', fn ($q) => $q->where('last_name', $last))->firstOrFail();
        DB::table('pms_rate_amounts')->insert(['rate_code_id' => DB::table('pms_rate_codes')->where('code', 'BAR')->value('id'), 'room_type_id' => null,
            'date_from' => '2026-11-01', 'date_to' => '2026-12-31', 'price_2' => 4200, 'price_1' => 4000]);   // örnek veride gece fiyatı elle; yeni geceler için
        $ozturk = $res('Öztürk');   // 01.12–04.12, 1108 YMF, bugün gelecek
        $target = DB::table('pms_rooms')->where('number', '1109')->value('id');

        // Başka odaya ve iki gün ileri
        $this->actingAs($this->user)->post("/oda-plani/{$ozturk->id}/tasi", ['room_id' => $target, 'arrival_date' => '2026-12-03', 'departure_date' => '2026-12-06'])
            ->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));
        $ozturk->refresh();
        $this->assertSame([(int) $target, '2026-12-03', '2026-12-06', 3], [(int) $ozturk->room_id, $ozturk->arrival_date->toDateString(), $ozturk->departure_date->toDateString(), $ozturk->nightRows()->where('room_id', $target)->count()]);

        // İçerideki misafirin girişi değişmez; çıkışı uzatılabilir
        $yilmaz = $res('Yılmaz');
        $this->actingAs($this->user)->post("/oda-plani/{$yilmaz->id}/tasi", ['room_id' => $yilmaz->room_id, 'arrival_date' => $yilmaz->arrival_date->addDay()->toDateString(),
            'departure_date' => $yilmaz->departure_date->addDay()->toDateString()])->assertSessionHas('error');
        $this->actingAs($this->user)->post("/oda-plani/{$yilmaz->id}/tasi", ['room_id' => $yilmaz->room_id, 'arrival_date' => $yilmaz->arrival_date->toDateString(),
            'departure_date' => $yilmaz->departure_date->addDays(2)->toDateString()])->assertSessionHas('status');
        $this->assertSame($yilmaz->departure_date->addDays(2)->toDateString(), $yilmaz->fresh()->departure_date->toDateString());

        // Dolu odaya taşınamaz, hiçbir şey değişmez
        $occupied = DB::table('pms_rooms')->where('number', '2410')->value('id');   // Müller içeride
        $this->actingAs($this->user)->post("/oda-plani/{$ozturk->id}/tasi", ['room_id' => $occupied, 'arrival_date' => '2026-12-03', 'departure_date' => '2026-12-06'])->assertSessionHas('error');
        $this->assertSame((int) $target, (int) $ozturk->fresh()->room_id);
    }

    public function test_new_reservation_from_empty_cells(): void
    {
        $room = DB::table('pms_rooms')->where('number', '1109')->first();
        $html = $this->actingAs($this->user)->get("/rezervasyon/yeni?oda={$room->id}&gelis=03.12.2026&cikis=06.12.2026")->assertOk()->getContent();
        $this->assertStringContainsString('value="03.12.2026"', $html);
        $this->assertStringContainsString('value="06.12.2026"', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$room->id.'"[^>]*selected/', $html);
    }
}
