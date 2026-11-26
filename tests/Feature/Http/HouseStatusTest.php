<?php

namespace Tests\Feature\Http;

use App\Domain\Housekeeping\Services\HouseStatusService;
use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class HouseStatusTest extends DomainTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
    }

    private function user(string $role): User
    {
        $user = User::create(['username' => 'U'.$role, 'name' => $role, 'email' => strtolower($role).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', $role)->value('id')]);

        return $user;
    }

    public function test_figures_follow_reservations_and_rooms(): void
    {
        $s = app(HouseStatusService::class)->compute($this->property);
        $inHouse = Reservation::where('status', 'in_house')->where('is_day_use', false);

        $this->assertSame(155, $s['total']['total']);
        $this->assertSame((clone $inHouse)->count(), $s['movement']['current_occupancy']['rooms']);
        $this->assertSame((int) (clone $inHouse)->sum('adults'), $s['movement']['current_occupancy']['pax']);
        $this->assertSame(Reservation::where('status', 'in_house')->where('departure_date', '<=', '2026-12-01')->count(), $s['movement']['expected_departures']['rooms']);
        $this->assertSame(Reservation::whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', '2026-12-01')->count(), $s['movement']['expected_arrivals']['rooms']);
        $this->assertSame(155, $s['housekeeping']['clean']['total'] + $s['housekeeping']['dirty']['total']);
        $this->assertSame($s['movement']['current_occupancy']['rooms'], $s['housekeeping']['total']['occupied']);
        $this->assertSame($s['end_of_day']['in_house_tonight']['rooms'], $s['end_of_day']['individuals']['rooms']);
        $this->assertSame($s['total']['to_sell'] - $s['end_of_day']['in_house_tonight']['rooms'], $s['end_of_day']['available_tonight']);
        $this->assertGreaterThan(0, $s['end_of_day']['adr_gross']);

        $withChildren = app(HouseStatusService::class)->compute($this->property, true);
        $this->assertSame($s['movement']['current_occupancy']['pax'] + $s['movement']['current_occupancy']['children'], $withChildren['movement']['current_occupancy']['pax']);
    }

    public function test_out_of_order_reduces_rooms_to_sell(): void
    {
        $room = DB::table('pms_rooms')->where('number', '1120')->first();
        DB::table('pms_room_outages')->insert(['property_id' => $this->property, 'room_id' => $room->id, 'type' => 'ooo', 'date_from' => '2026-12-01', 'date_to' => '2026-12-05',
            'reason_code_id' => DB::table('pms_reason_codes')->where('category', 'room_status')->where('code', 'OOO')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        $s = app(HouseStatusService::class)->compute($this->property);
        $this->assertSame(154, $s['total']['to_sell']);
        $this->assertSame(1, $s['housekeeping']['out_of_order']['total']);
    }

    public function test_screen_and_permissions(): void
    {
        $html = $this->actingAs($this->user('admin'))->get('/house-status')->assertOk()->getContent();
        foreach (['House Status', 'Total to sell', 'Expected Arrivals', 'Arrival Rooms assigned', 'Clean Rooms', 'Rooms in Queue', 'Average Room Rate gross', 'In House Tonight', 'Add Children to Pax'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString(route('reservations.navigator', ['f' => 'gelecek']), $html);   // aşağı ok: listeye git
        $this->actingAs($this->user('HKS'))->get('/house-status')->assertOk();                          // kat hizmetleri de görür
        $this->actingAs($this->user('IFC'))->get('/house-status')->assertForbidden();
    }
}
