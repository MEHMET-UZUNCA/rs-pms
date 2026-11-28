<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use App\Support\BusinessDateInput;
use App\Support\UserPreferences;
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
        $this->assertSame('occupied', DB::table('pms_rooms')->where('number', '1102')->value('fo_status'));
        $this->assertSame('dirty', DB::table('pms_rooms')->where('number', '1107')->value('hk_status'));   // çıkış yapan odası kirli
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
        $this->actingAs($this->user)->get('/rezervasyon?oda=1104')->assertSee('Ivanova, Elena')->assertDontSee('Yılmaz, Ahmet');
        $this->actingAs($this->user)->get('/rezervasyon?firma=holding')->assertSee('Smith, John')->assertSee('Wilson, James')->assertDontSee('Ivanova');
        $this->actingAs($this->user)->get('/rezervasyon?gelis=%2B5')->assertSee('Schmidt, Anna')->assertSee('06.12.2026')->assertDontSee('Şahin, Elif');
        $no = $this->res('Petrov')->confirmation_number;
        $this->actingAs($this->user)->get('/rezervasyon?no='.$no)->assertSee('Petrov, Sergei')->assertDontSee('Öztürk');
    }

    public function test_summary_panel_shows_folio_profiles_and_contact(): void
    {
        $smith = $this->res('Smith');
        $this->actingAs($this->user)->get("/rezervasyon/{$smith->id}/ozet")->assertOk()
            ->assertSee('john.smith@example.com')->assertSee('Örnek Holding A.Ş.')->assertSee('window 1')->assertSee('1103');

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
        $this->actingAs($this->user)->post("/rezervasyon/{$petrov->id}/giris")->assertSessionHas('error', 'Assign a room before check-in.');
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

    public function test_cancel_from_navigator_with_reason(): void
    {
        $res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();   // +2, gelecek
        $inHouse = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Yılmaz'))->firstOrFail();
        $html = $this->actingAs($this->user)->get('/rezervasyon?f=ileri')->assertOk()->getContent();
        $this->assertStringContainsString('id="cancel-dialog"', $html);
        $this->assertStringContainsString('data-url-cancel="'.route('reservations.cancel', $res).'"', $html);
        $this->assertStringNotContainsString('data-url-cancel="'.route('reservations.cancel', $inHouse).'"', $this->actingAs($this->user)->get('/rezervasyon?f=iceride')->getContent());

        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/iptal", [])->assertSessionHasErrors('reason_code_id');
        $reason = DB::table('pms_reason_codes')->where('property_id', $this->property)->where('category', 'cancellation')->value('id');
        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/iptal", ['reason_code_id' => $reason])->assertSessionHas('status');
        $res->refresh();
        $this->assertSame(['cancelled', (int) $reason], [$res->status, (int) $res->cancel_reason_code_id]);
        $this->assertNotNull($res->cancellation_number);
        $this->assertSame(0, $res->nightRows()->where('status', 'expected')->count());
        $this->assertSame('cancelled', DB::table('pms_reservation_status_logs')->where('reservation_id', $res->id)->orderByDesc('id')->value('to_status'));

        // İçerideki rezervasyon iptal edilemez
        $this->actingAs($this->user)->post("/rezervasyon/{$inHouse->id}/iptal", ['reason_code_id' => $reason])->assertSessionHas('error');
        $this->assertSame('in_house', $inHouse->fresh()->status);
    }

    public function test_operational_views_tentative_cancelled_no_show(): void
    {
        $res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();
        $res->update(['status' => 'tentative']);
        $this->actingAs($this->user)->get('/rezervasyon?f=bekleyen')->assertOk()->assertSee('Arslan, Can')->assertSee('Tentative');
        $reason = DB::table('pms_reason_codes')->where('property_id', $this->property)->where('category', 'cancellation')->value('id');
        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/iptal", ['reason_code_id' => $reason]);
        $this->actingAs($this->user)->get('/rezervasyon?f=iptal')->assertSee('Arslan, Can');
        $this->actingAs($this->user)->get('/rezervasyon?f=bekleyen')->assertDontSee('Arslan, Can');
        $this->actingAs($this->user)->get('/rezervasyon?f=noshow')->assertOk();
        $this->actingAs($this->user)->get('/rezervasyon?f=gidecek')->assertSee('data-url-checkout=', false);
    }

    public function test_user_column_and_panel_preferences(): void
    {
        $html = $this->actingAs($this->user)->get('/rezervasyon')->getContent();
        $this->assertStringContainsString('id="columns-dialog"', $html);
        $this->assertStringNotContainsString('<th >Rate Code</th>', $html);
        preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', explode('id="res-grid"', $html)[1], $m);
        $this->assertSame(['Room', 'Guest', 'Arrival', 'Departure', 'Room Type', 'Pax', 'Company / Agent', 'Status', 'Balance', 'Conf. No.'], array_map('trim', array_map('strip_tags', explode('</th>', trim(str_replace('</th>', '</th>', $m[1])), -1))));

        $this->actingAs($this->user)->post('/rezervasyon/tercihler', ['cols' => ['guest', 'room', 'rate_code', 'balance'], 'panel' => 'none'])->assertSessionHas('status');
        $html = $this->actingAs($this->user)->get('/rezervasyon')->getContent();
        preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', explode('id="res-grid"', $html)[1], $m);
        $this->assertSame(['Guest', 'Room', 'Rate Code', 'Balance'], array_map('trim', array_map('strip_tags', explode('</th>', $m[1], -1))));
        $this->assertMatchesRegularExpression('/id="res-detail"[^>]*hidden/', $html);

        // Başka kullanıcı etkilenmez; varsayılana dönülür
        $this->actingAs($this->user)->post('/rezervasyon/tercihler', ['reset' => '1']);
        preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', explode('id="res-grid"', $this->actingAs($this->user)->get('/rezervasyon')->getContent())[1], $m);
        $this->assertCount(10, explode('</th>', $m[1], -1));
        $this->actingAs($this->user)->post('/rezervasyon/tercihler', ['cols' => ['nope']])->assertSessionHasErrors('cols.0');
    }

    public function test_reinstate_and_undo_check_in(): void
    {
        $res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();   // +2, gelecek
        $nights = $res->nightRows()->count();
        $reason = DB::table('pms_reason_codes')->where('property_id', $this->property)->where('category', 'cancellation')->value('id');
        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/iptal", ['reason_code_id' => $reason])->assertSessionHas('status');
        $this->assertStringContainsString('data-url-reinstate="'.route('reservations.reinstate', $res).'"', $this->actingAs($this->user)->get('/rezervasyon?f=iptal')->getContent());

        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/geri-al")->assertSessionHas('status');
        $res->refresh();
        $this->assertSame(['confirmed', null, $nights], [$res->status, $res->cancelled_at, $res->nightRows()->where('status', 'expected')->count()]);
        $this->assertSame(['cancelled', 'confirmed'], DB::table('pms_reservation_status_logs')->where('reservation_id', $res->id)->orderByDesc('id')->limit(2)->pluck('to_status')->reverse()->values()->all());
        $this->actingAs($this->user)->post("/rezervasyon/{$res->id}/geri-al")->assertSessionHas('error');   // zaten kesin

        // Undo check-in: bugün giriş yapan, folyosunda kayıt yok
        $ozturk = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Öztürk'))->firstOrFail();   // bugün gelecek, oda 1108
        $this->actingAs($this->user)->post("/rezervasyon/{$ozturk->id}/giris")->assertSessionHas('status');
        $this->assertSame('in_house', $ozturk->fresh()->status);
        $this->assertStringContainsString('data-url-undo="', $this->actingAs($this->user)->get('/rezervasyon?f=iceride')->getContent());
        $this->actingAs($this->user)->post("/rezervasyon/{$ozturk->id}/giris-geri-al")->assertSessionHas('status');
        $ozturk->refresh();
        $this->assertSame(['confirmed', null], [$ozturk->status, $ozturk->check_in_business_date]);
        $this->assertSame('vacant', DB::table('pms_rooms')->where('id', $ozturk->room_id)->value('fo_status'));

        // Kayıt atılmışsa geri alınamaz
        $yilmaz = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Yılmaz'))->firstOrFail();
        $this->actingAs($this->user)->post("/rezervasyon/{$yilmaz->id}/giris-geri-al")->assertSessionHas('error');
    }

    public function test_grouping_by_room_type_with_subtotals_is_remembered(): void
    {
        $inHouse = Reservation::where('status', 'in_house')->with('roomType')->get();
        $html = $this->actingAs($this->user)->get('/rezervasyon?f=iceride&grup=room_type')->assertOk()->getContent();
        $types = $inHouse->groupBy(fn ($r) => $r->roomType->code);
        $this->assertSame($types->count(), substr_count($html, 'data-group-row'));
        foreach ($types as $code => $list) {
            $pax = $list->sum('adults') + $list->sum('children');
            $this->assertStringContainsString("<b>{$code}</b>", $html);
            $this->assertStringContainsString($list->count().' res. · '.$pax.' pax', $html);
        }
        // Tercih saklanır: süzgeç değişince de gruplu; "none" kapatır
        $this->assertSame('room_type', UserPreferences::get($this->user, 'navigator.group'));
        $this->assertStringContainsString('data-group-row', $this->actingAs($this->user)->get('/rezervasyon?f=iceride')->getContent());
        $this->assertStringNotContainsString('data-group-row', $this->actingAs($this->user)->get('/rezervasyon?f=iceride&grup=none')->getContent());
    }
}
