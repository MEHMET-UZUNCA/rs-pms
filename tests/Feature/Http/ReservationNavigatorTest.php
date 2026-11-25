<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ReservationNavigatorTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026; içeride, gelecek, giden, çıkmış, ileri, iptal, FA
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function res(string $lastName): Reservation
    {
        return Reservation::whereHas('profile', fn ($q) => $q->where('last_name', $lastName))->firstOrFail();
    }

    public function test_demo_data_is_built_through_real_services(): void
    {
        $this->assertSame(6, Reservation::where('status', 'in_house')->count());
        $this->assertSame(2, Reservation::where('status', 'checked_out')->count());
        $this->assertSame(1, Reservation::where('status', 'cancelled')->count());
        $this->assertSame('occupied', DB::table('pms_rooms')->where('number', '101')->value('fo_status'));
        $this->assertSame('dirty', DB::table('pms_rooms')->where('number', '103')->value('hk_status'));   // çıkış yapan odası kirli
        $this->assertSame(3 * 4200 + 850.0, (float) $this->res('Yılmaz')->folios()->sum('balance'));
    }

    public function test_default_filter_is_in_house_and_quick_filters_work(): void
    {
        $this->actingAs($this->user)->get('/rezervasyon')->assertOk()
            ->assertSee('Yılmaz, Ahmet')->assertSee('Cohen, David')->assertDontSee('Öztürk, Zeynep')->assertSee('01.12.2026')
            ->assertSee('data-hotkey="alt+1"', false)->assertSee('data-hotkey="alt+0"', false);

        $this->actingAs($this->user)->get('/rezervasyon?f=gelecek')->assertSee('Öztürk, Zeynep')->assertSee('Levi, Noa')->assertDontSee('Yılmaz, Ahmet');
        $this->actingAs($this->user)->get('/rezervasyon?f=gidecek')->assertSee('Smith, John')->assertDontSee('Müller, Thomas');   // 2 gece, bugün çıkıyor
        $this->actingAs($this->user)->get('/rezervasyon?f=cikmis')->assertSee('Kaya, Mehmet')->assertSee('Brown, Emma')->assertDontSee('Smith, John');
        $this->actingAs($this->user)->get('/rezervasyon?f=ileri')->assertSee('Şahin, Elif')->assertDontSee('Öztürk, Zeynep')->assertDontSee('Aydın, Burak');
        $this->actingAs($this->user)->get('/rezervasyon?f=fa')->assertSee('Ön büro kasa hesabı')->assertDontSee('Örnek Holding toplantısı');
        $this->actingAs($this->user)->get('/rezervasyon?f=fa-ileri')->assertSee('Örnek Holding toplantısı');
    }

    public function test_query_fields_search_all_statuses(): void
    {
        $this->actingAs($this->user)->get('/rezervasyon?ad=ayd')->assertSee('Aydın, Burak')->assertSee('Cancelled');
        $this->actingAs($this->user)->get('/rezervasyon?oda=201')->assertSee('Ivanova, Elena')->assertDontSee('Yılmaz, Ahmet');
        $this->actingAs($this->user)->get('/rezervasyon?firma=holding')->assertSee('Smith, John')->assertSee('Wilson, James')->assertDontSee('Ivanova');
        $this->actingAs($this->user)->get('/rezervasyon?gelis=%2B5')->assertSee('Schmidt, Anna')->assertSee('06.12.2026')->assertDontSee('Şahin, Elif');
        $no = $this->res('Petrov')->confirmation_number;
        $this->actingAs($this->user)->get('/rezervasyon?no='.$no)->assertSee('Petrov, Sergei')->assertDontSee('Öztürk');
    }

    public function test_summary_panel_shows_folio_profiles_and_contact(): void
    {
        $smith = $this->res('Smith');
        $this->actingAs($this->user)->get("/rezervasyon/{$smith->id}/ozet")->assertOk()
            ->assertSee('john.smith@example.com')->assertSee('Örnek Holding A.Ş.')->assertSee('window 1')->assertSee('102');

        $fa = DB::table('pms_folios')->where('folio_number', 'FA-DEMO-1')->value('id');
        $this->actingAs($this->user)->get("/rezervasyon/hesap/{$fa}/ozet")->assertOk()->assertSee('Otopark (örnek)')->assertSee('300,00');
    }

    public function test_check_in_from_navigator(): void
    {
        $oz = $this->res('Öztürk');   // bugün geliyor, oda 104 atanmış
        $this->actingAs($this->user)->get('/rezervasyon?f=gelecek')->assertSee(route('reservations.check-in', $oz), false);
        $this->actingAs($this->user)->post("/rezervasyon/{$oz->id}/giris")->assertSessionHas('status');
        $this->assertSame('in_house', $oz->fresh()->status);

        $petrov = $this->res('Petrov');   // oda atanmamış
        $this->actingAs($this->user)->post("/rezervasyon/{$petrov->id}/giris")->assertSessionHas('error', 'Girişten önce oda atanmalı.');
    }

    public function test_permissions(): void
    {
        $clerk = User::create(['username' => 'X', 'name' => 'X', 'email' => 'x@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->insertGetId(['code' => 'none', 'name' => 'Yetkisiz'])]);
        $this->actingAs($clerk)->get('/rezervasyon')->assertForbidden();
    }

    public function test_fidelio_date_input(): void
    {
        $bd = CarbonImmutable::parse('2026-11-25');
        $this->assertSame('2026-11-25', BusinessDateInput::parse('t', $bd)->toDateString());
        $this->assertSame('2026-11-26', BusinessDateInput::parse('+1', $bd)->toDateString());
        $this->assertSame('2026-11-18', BusinessDateInput::parse('-7', $bd)->toDateString());
        $this->assertSame('2026-12-03', BusinessDateInput::parse('3.12', $bd)->toDateString());
        $this->assertSame('2027-01-05', BusinessDateInput::parse('05.01.27', $bd)->toDateString());
        $this->assertSame('2026-11-30', BusinessDateInput::parse('30', $bd)->toDateString());
        $this->assertNull(BusinessDateInput::parse('31.02.2026', $bd));
        $this->assertNull(BusinessDateInput::parse('abc', $bd));
    }
}
