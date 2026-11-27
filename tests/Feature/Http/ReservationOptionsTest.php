<?php

namespace Tests\Feature\Http;

use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ReservationOptionsTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function hk(string $room): string
    {
        return (string) DB::table('pms_rooms')->where('number', $room)->value('hk_status');
    }

    public function test_defaults_follow_rocks_fidelio_settings(): void
    {
        $o = ReservationOptions::get($this->property);
        $this->assertSame(['14:00', '12:00', 'none', 'dirty', 'none', 'none'], [$o['check_in_time'], $o['check_out_time'],
            $o['status_after_check_in'], $o['status_after_check_out'], $o['status_after_audit_occupied'], $o['status_after_no_show']]);
        $this->assertNotSame('dirty', $this->hk('1102'));   // girişte kat durumu değişmez (yalnız check-out Dirty)
        $this->assertTrue($o['meals_departure_day']['breakfast']);
        $this->assertFalse($o['meals_first_day']['breakfast']);
    }

    public function test_night_audit_sets_occupied_rooms_by_option(): void
    {
        DB::table('pms_rooms')->where('number', '1102')->update(['hk_status' => 'inspected']);
        NightAuditAnswers::save($this->property, ['arrivals_pending' => 'continue'], null);
        app(NightAuditService::class)->run($this->property, null, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'test'));
        $this->assertSame('inspected', $this->hk('1102'));   // varsayılan: gece kapanışı kat durumunu değiştirmez

        ReservationOptions::save($this->property, ['status_after_audit_occupied' => 'dirty'], null);
        app(NightAuditService::class)->run($this->property, null, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'test'));
        $this->assertSame('dirty', $this->hk('1102'));
    }

    public function test_options_screen_saves(): void
    {
        $this->actingAs($this->user)->get('/ayarlar/rezervasyon-secenekleri')->assertOk()
            ->assertSee('Room Status after N/A if occupied')->assertSee('No Action')->assertSee('Meal Plan defaults');

        $this->actingAs($this->user)->put('/ayarlar/rezervasyon-secenekleri', [
            'check_in_time' => '15:00', 'check_out_time' => '11:00', 'day_use_check_in_time' => '10:00', 'day_use_check_out_time' => '18:00',
            'status_after_check_in' => 'none', 'status_after_check_out' => 'dirty', 'status_after_audit_occupied' => 'none', 'status_after_no_show' => 'none', 'dirty_room_check_in' => 'block',
            'queue_rooms' => '1', 'meal_plan' => '1', 'meals_first_day' => ['dinner' => '1'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $o = ReservationOptions::get($this->property);
        $this->assertSame(['15:00', 'none', 'none'], [$o['check_in_time'], $o['status_after_check_in'], $o['status_after_audit_occupied']]);
        $this->assertSame(['breakfast' => false, 'lunch' => false, 'dinner' => true], $o['meals_first_day']);
        $this->assertFalse($o['meals_departure_day']['breakfast']);

        $this->actingAs($this->user)->put('/ayarlar/rezervasyon-secenekleri', ['check_in_time' => '25:00'])->assertSessionHasErrors('check_in_time');
    }
}
