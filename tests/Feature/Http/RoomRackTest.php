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
        $this->assertSame(24, substr_count($html, 'data-room="'));
        $this->assertStringContainsString('01.12', $html);
        $this->assertStringContainsString('<br>14.12', $html);   // 14 gün
        $this->assertStringNotContainsString('<br>15.12', $html);
        $this->assertStringContainsString('Yılmaz, Ahmet', $html);   // 101 içeride
        $this->assertStringContainsString('data-kind="in_house"', $html);
        $this->assertStringContainsString('data-kind="confirmed"', $html);   // 104 Öztürk, bugün gelecek
        $this->assertStringContainsString('rounded-l-none', $html);   // başlangıçtan önce başlayan konaklama
        $this->assertStringContainsString('data-hotkey="ctrl+l"', $html);
    }

    public function test_span_navigation_and_filters(): void
    {
        $this->actingAs($this->user)->get('/oda-plani?gun=7')->assertSee('<br>07.12', false)->assertDontSee('<br>08.12', false);
        $this->actingAs($this->user)->get('/oda-plani?gun=30&bas=%2B7')->assertSee('<br>08.12', false)->assertSee('<br>06.01', false)->assertDontSee('<br>07.12', false);

        $suite = DB::table('pms_room_types')->where('code', 'SUI')->value('id');
        $html = $this->actingAs($this->user)->get("/oda-plani?tip={$suite}")->getContent();
        $this->assertSame(4, substr_count($html, 'data-room="'));
        $this->assertStringContainsString('Johnson, Mark', $html);   // 302, bugün geliyor (301 Cohen bugün çıkıyor: çıkış günü planda yok)
        $this->assertStringNotContainsString('Yılmaz, Ahmet', $html);
    }

    public function test_outage_and_daily_summary(): void
    {
        $room = DB::table('pms_rooms')->where('number', '108')->value('id');
        $reason = DB::table('pms_reason_codes')->insertGetId(['property_id' => $this->property, 'category' => 'outage', 'code' => 'AC', 'name' => 'Klima']);
        DB::table('pms_room_outages')->insert(['property_id' => $this->property, 'room_id' => $room, 'type' => 'OOO', 'date_from' => '2026-12-02',
            'date_to' => '2026-12-05', 'reason_code_id' => $reason, 'note' => 'Klima arızası']);

        $html = $this->actingAs($this->user)->get('/oda-plani')->getContent();
        $this->assertStringContainsString('data-kind="ooo"', $html);
        $this->assertStringContainsString('OOO · Klima arızası', $html);
        // Bu gece: 4 içeride (Cohen ve Smith bugün çıkıyor) + 2 odalı gelen = 6 dolu (%25); 2 gelen odasız (Petrov, Levi)
        $this->assertMatchesRegularExpression('/tabular-nums">6<span class="text-muted"> · 25%/', preg_replace('/\s+/', ' ', $html));
        $this->assertMatchesRegularExpression('/text-danger font-semibold">2</', preg_replace('/\s+/', ' ', $html));
    }
}
