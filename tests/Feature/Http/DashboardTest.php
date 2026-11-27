<?php

namespace Tests\Feature\Http;

use App\Domain\FrontOffice\Services\DashboardService;
use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class DashboardTest extends DomainTestCase
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

    public function test_dashboard_shows_today_forecast_and_lists(): void
    {
        $bd = DB::table('pms_properties')->where('id', $this->property)->value('current_business_date');
        $inHouse = DB::table('pms_reservations')->where('property_id', $this->property)->where('status', 'in_house')->count();
        $arrivals = DB::table('pms_reservations')->where('property_id', $this->property)->whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', $bd)->count();

        $html = $this->actingAs($this->user('FOA'))->get('/')->assertOk()->getContent();
        foreach (['Front Office Dashboard', 'Occupancy forecast (14 days)', 'Arrivals today', 'Departures today', 'Last closed day', 'No night audit has been closed yet.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertMatchesRegularExpression('/data-tile="in_house".*?data-value>'.$inHouse.'</s', $html);
        $this->assertMatchesRegularExpression('/data-tile="arrivals".*?data-value>0 \/ '.$arrivals.'</s', $html);
        $this->assertSame(14, substr_count(explode('</table>', explode('data-forecast', $html)[1])[0], 'class="whitespace-nowrap"'));
        $arrival = DB::table('pms_reservations as r')->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')->where('r.property_id', $this->property)
            ->whereIn('r.status', ['confirmed', 'tentative'])->where('r.arrival_date', $bd)->value('g.display_name');
        $this->assertStringContainsString(e($arrival), explode('data-list="departures"', explode('data-list="arrivals"', $html)[1])[0]);

        // Gece kapanışından sonra son kapanan gün dolar
        NightAuditAnswers::save($this->property, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'continue'), null);
        DB::table('pms_reservations')->where('property_id', $this->property)->where('status', 'in_house')->where('departure_date', '<=', $bd)->update(['departure_date' => '2026-12-10']);
        app(NightAuditService::class)->run($this->property, null, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'test'));
        $html = $this->actingAs($this->user('FOM'))->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('01.12.2026', $html);
        $this->assertStringContainsString('Room revenue', $html);
        $this->assertStringNotContainsString('No night audit has been closed yet.', $html);
    }

    public function test_forecast_counts_sold_tentative_and_ooo(): void
    {
        $days = app(DashboardService::class)->forecast($this->property, CarbonImmutable::parse('2026-12-01'), 100);
        $this->assertCount(14, $days);
        $sold = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')->where('n.stay_date', '2026-12-01')
            ->where('n.status', '!=', 'cancelled')->where('r.is_day_use', false)->whereIn('r.status', ['confirmed', 'in_house'])->count();
        $this->assertSame($sold, $days[0]['sold']);
        $this->assertSame('2026-12-14', $days[13]['date']->toDateString());
    }

    public function test_without_front_office_permission_shows_screen_list(): void
    {
        $this->actingAs($this->user('IFC'))->get('/')->assertOk()->assertDontSee('Front Office Dashboard')->assertSee('data-available-screens', false);
    }

    public function test_dashboard_rows_have_actions(): void
    {
        $html = $this->actingAs($this->user('FOA'))->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="row-menu"', $html);
        $arrivals = explode('data-list="departures"', explode('data-list="arrivals"', $html)[1])[0];
        preg_match_all("/data-row-actions='([^']*)'/", $arrivals, $m);
        $this->assertNotEmpty($m[1]);
        $keys = array_keys(json_decode(html_entity_decode($m[1][0]), true));
        $this->assertContains('card', $keys);
        $this->assertTrue(in_array('checkin', $keys, true) || in_array('room', $keys, true));   // odası varsa giriş, yoksa oda ata
        preg_match_all("/data-row-actions='([^']*)'/", explode('data-list="departures"', $html)[1], $d);
        if ($d[1]) {
            $this->assertContains('checkout', array_keys(json_decode(html_entity_decode($d[1][0]), true)));
        }
    }
}
