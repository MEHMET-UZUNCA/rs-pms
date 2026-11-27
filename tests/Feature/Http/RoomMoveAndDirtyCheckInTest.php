<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class RoomMoveAndDirtyCheckInTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ON', 'name' => 'Ön büro', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function res(string $last): Reservation
    {
        return Reservation::whereHas('profile', fn ($q) => $q->where('last_name', $last))->firstOrFail();
    }

    public function test_dirty_room_check_in_allow_warn_block(): void
    {
        $ozturk = $this->res('Öztürk');   // bugün gelecek, 1108
        DB::table('pms_rooms')->where('id', $ozturk->room_id)->update(['hk_status' => 'dirty']);

        ReservationOptions::save($this->property, ['dirty_room_check_in' => 'block'], null);
        $this->actingAs($this->user)->post("/rezervasyon/{$ozturk->id}/giris")->assertSessionHas('error');
        $this->assertSame('confirmed', $ozturk->fresh()->status);

        ReservationOptions::save($this->property, ['dirty_room_check_in' => 'warn'], null);
        $this->actingAs($this->user)->post("/rezervasyon/{$ozturk->id}/giris")->assertSessionHas('status')->assertSessionHas('error');
        $this->assertSame('in_house', $ozturk->fresh()->status);

        $this->actingAs($this->user)->get('/ayarlar/rezervasyon-secenekleri')->assertSee('Check in to a dirty room')->assertSee('Do not allow');
    }

    public function test_room_move_reason_and_list(): void
    {
        $yilmaz = $this->res('Yılmaz');   // içeride 1102
        $target = DB::table('pms_rooms')->where('number', '1109')->value('id');
        $reason = DB::table('pms_reason_codes')->where('category', 'room_move')->where('code', 'GUEST')->value('id');
        $this->actingAs($this->user)->put("/rezervasyon/{$yilmaz->id}", [
            'arrival_date' => $yilmaz->arrival_date->format('d.m.Y'), 'departure_date' => $yilmaz->departure_date->format('d.m.Y'), 'adults' => $yilmaz->adults,
            'room_type_id' => $yilmaz->room_type_id, 'room_id' => $target, 'rate_code_id' => $yilmaz->rate_code_id, 'reservation_type_id' => $yilmaz->reservation_type_id,
            'market_segment_id' => $yilmaz->market_segment_id, 'source_code_id' => $yilmaz->source_code_id, 'guarantee_type' => $yilmaz->guarantee_type ?? 'none',
            'room_move_reason_id' => $reason,
        ])->assertSessionHasNoErrors();
        $move = DB::table('pms_reservation_room_moves')->where('reservation_id', $yilmaz->id)->sole();
        $this->assertSame([(int) $target, (int) $reason], [(int) $move->to_room_id, (int) $move->reason_code_id]);

        $html = $this->actingAs($this->user)->get('/oda-degisimleri')->assertOk()->getContent();
        $this->assertStringContainsString('Room Moves', $html);
        $this->assertStringContainsString('Yılmaz, Ahmet', $html);
        $this->assertStringContainsString('1109', $html);

        $maint = DB::table('pms_reason_codes')->where('category', 'room_move')->where('code', 'MAINT')->value('id');
        $this->actingAs($this->user)->post("/oda-degisimleri/{$move->id}/neden", ['reason_code_id' => $maint])->assertSessionHas('status');
        $this->assertSame((int) $maint, (int) DB::table('pms_reservation_room_moves')->where('id', $move->id)->value('reason_code_id'));
        $this->actingAs($this->user)->get('/oda-degisimleri?bas=10.12.2026')->assertSee('No room moves in this period.');
    }
}
