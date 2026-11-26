<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class QueueRoomsTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    /** Bugün gelecek, odası atanmış bir rezervasyon; oda kirli. */
    private function arrival(): Reservation
    {
        $r = Reservation::whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', '2026-12-01')->whereNotNull('room_id')->first()
            ?? tap(Reservation::whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', '2026-12-01')->firstOrFail(),
                fn ($r) => $r->update(['room_id' => DB::table('pms_rooms')->where('number', '1130')->value('id')]));
        DB::table('pms_rooms')->where('id', $r->room_id)->update(['hk_status' => 'dirty', 'fo_status' => 'vacant']);

        return $r->fresh();
    }

    public function test_queue_flow_ready_badge_and_check_in(): void
    {
        $r = $this->arrival();
        $nav = $this->actingAs($this->user)->get('/rezervasyon?f=gelecek')->getContent();
        $this->assertStringContainsString('data-url-queue="'.route('queue.add', $r).'"', $nav);

        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/kuyruk")->assertSessionHas('status');
        $this->assertNotNull($r->fresh()->queued_at);

        $html = $this->actingAs($this->user)->get('/bekleyen-odalar')->assertOk()->getContent();
        $this->assertStringContainsString('Not ready', $html);
        $this->assertStringContainsString('Queue 1 · ready 0', $html);          // üst çubuk
        $this->assertStringNotContainsString('data-url-checkin', $html);       // oda hazır değil

        DB::table('pms_rooms')->where('id', $r->room_id)->update(['hk_status' => 'inspected']);   // kat hizmetleri odayı hazırladı
        $html = $this->actingAs($this->user)->get('/bekleyen-odalar')->getContent();
        $this->assertStringContainsString('Queue 1 · ready 1', $html);
        $this->assertStringContainsString('data-url-checkin="'.route('reservations.check-in', $r->id).'"', $html);

        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/giris")->assertSessionHas('status');
        $this->assertSame('in_house', $r->fresh()->status);
        $this->assertNull($r->fresh()->queued_at);                             // girişte kuyruktan çıkar
        $this->actingAs($this->user)->get('/bekleyen-odalar')->assertSee('No guests waiting.')->assertDontSee('data-queue-badge', false);
    }

    public function test_only_todays_arrivals_and_remove(): void
    {
        $inHouse = Reservation::where('status', 'in_house')->firstOrFail();
        $this->actingAs($this->user)->post("/rezervasyon/{$inHouse->id}/kuyruk")->assertSessionHas('error');

        $r = $this->arrival();
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/kuyruk");
        $this->actingAs($this->user)->delete("/rezervasyon/{$r->id}/kuyruk")->assertSessionHas('status');
        $this->assertNull($r->fresh()->queued_at);
    }

    public function test_badge_follows_reservation_option(): void
    {
        $r = $this->arrival();
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/kuyruk");
        $this->actingAs($this->user)->get('/')->assertSee('data-queue-badge', false);
        \App\Domain\Reservations\Support\ReservationOptions::save($this->property, ['queue_rooms' => false], null);
        $this->actingAs($this->user)->get('/')->assertDontSee('data-queue-badge', false);
    }
}
