<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\MealPlanService;
use App\Domain\Reservations\Services\ReservationService;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class MultiboardTest extends DomainTestCase
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

    private function board(string $code): int
    {
        return (int) DB::table('pms_packages')->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    /** 2 yetişkin + 1 çocuk, 10.12 – 13.12 (3 gece), yarım pansiyon (HBD) fiyat kodu. */
    private function stay(): Reservation
    {
        DB::table('pms_rate_codes')->where('code', 'TA-HB')->update(['package_id' => $this->board('HBD')]);
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'individual', 'display_name' => 'Board, Test', 'created_at' => now(), 'updated_at' => now()]);

        return app(ReservationService::class)->create([
            'property_id' => $this->property, 'profile_id' => $profile, 'room_type_id' => $this->id('pms_room_types', 'YMF'), 'rate_code_id' => $this->id('pms_rate_codes', 'TA-HB'),
            'arrival_date' => '2026-12-10', 'departure_date' => '2026-12-13', 'adults' => 2, 'child_bands' => [1 => 1], 'manual_amount' => 4000,
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'market_segment_id' => $this->id('pms_market_segments', 'TA'),
            'source_code_id' => $this->id('pms_source_codes', 'DIR'), 'currency_code' => 'TRY',
        ]);
    }

    private function day(array $forecast, string $date): array
    {
        return array_map(fn ($m) => $m['adults'] + $m['children'], $forecast[$date]);
    }

    public function test_nights_take_board_of_rate_code_and_meals_follow_rocks_rules(): void
    {
        $r = $this->stay();
        $this->assertSame([$this->board('HBD')], $r->nightRows()->pluck('package_id')->unique()->values()->all());

        $f = app(MealPlanService::class)->forecast($this->property, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-13'));
        // Giriş günü: yalnız akşam; ara günler: kahvaltı + akşam (HBD); çıkış günü: kahvaltı (öğle pansiyonda yok)
        $this->assertSame(['breakfast' => 0, 'lunch' => 0, 'dinner' => 3], $this->day($f, '2026-12-10'));
        $this->assertSame(['breakfast' => 3, 'lunch' => 0, 'dinner' => 3], $this->day($f, '2026-12-11'));
        $this->assertSame(['breakfast' => 3, 'lunch' => 0, 'dinner' => 0], $this->day($f, '2026-12-13'));
        $this->assertSame(2, $f['2026-12-11']['dinner']['adults']);
        $this->assertSame(1, $f['2026-12-11']['dinner']['children']);
    }

    public function test_board_change_mid_stay_from_card(): void
    {
        $r = $this->stay();
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}")->assertOk()->assertSee('data-meal-plan', false)->assertSee('HBD - Half Board Dinner');

        // Son gece (12.12) tam pansiyon
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/pansiyon", ['from' => '2026-12-12', 'to' => '2026-12-12', 'package_id' => $this->board('FB')])
            ->assertSessionHas('status', 'Board changed for 1 nights.');
        $this->assertSame($this->board('FB'), (int) $r->nightRows()->where('stay_date', '2026-12-12')->value('package_id'));

        $f = app(MealPlanService::class)->forecast($this->property, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-13'));
        $this->assertSame(['breakfast' => 3, 'lunch' => 3, 'dinner' => 3], $this->day($f, '2026-12-12'));   // kahvaltı önceki gece HBD, öğle+akşam FB
        $this->assertSame(['breakfast' => 3, 'lunch' => 3, 'dinner' => 0], $this->day($f, '2026-12-13'));   // çıkış günü: son gece FB

        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/pansiyon", ['from' => '2026-12-10', 'to' => '2026-12-12', 'package_id' => ''])->assertSessionHas('status');
        $this->assertSame(0, $r->nightRows()->whereNotNull('package_id')->count());   // No Board
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/pansiyon", ['from' => '2026-12-10', 'to' => '2026-12-12', 'package_id' => 999999])->assertSessionHas('error');
    }

    public function test_forecast_screen_and_options(): void
    {
        $this->stay();
        $html = $this->actingAs($this->user)->get('/ogun-sayilari?bas=10.12.2026&gun=7')->assertOk()->getContent();
        $this->assertStringContainsString('Meal Plan Forecast', $html);
        $this->assertMatchesRegularExpression('/data-date="2026-12-11".*?data-meal="breakfast">3</s', $html);
        $this->assertStringContainsString('HBD', $html);

        // Giriş günü kahvaltı da sayılsın
        ReservationOptions::save($this->property, ['meals_first_day' => ['breakfast' => true, 'lunch' => false, 'dinner' => true]], null);
        $f = app(MealPlanService::class)->forecast($this->property, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-10'));
        $this->assertSame(['breakfast' => 3, 'lunch' => 0, 'dinner' => 3], $this->day($f, '2026-12-10'));
    }
}
