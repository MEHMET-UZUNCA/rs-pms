<?php

namespace Tests\Feature\Http;

use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
use App\Domain\Reports\ReportCatalog;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ReportTest extends DomainTestCase
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

    public function test_every_report_renders_on_screen_print_and_csv(): void
    {
        $this->actingAs($this->user)->get('/raporlar')->assertOk()->assertSee('data-report-tree', false)->assertSee('Department Code Totals');
        foreach (array_keys(ReportCatalog::REPORTS) as $key) {
            $filters = ['r' => $key, 'goster' => 1, 'bas' => '01.11.2026', 'bit' => '15.12.2026'];
            $this->actingAs($this->user)->get('/raporlar?'.http_build_query($filters))->assertOk()->assertSee('data-report-table', false);
            $this->actingAs($this->user)->get('/raporlar?'.http_build_query(['format' => 'print'] + $filters))->assertOk()->assertSee(__('pms.reports.r.'.$key));
            $csv = $this->actingAs($this->user)->get('/raporlar?'.http_build_query(['format' => 'csv'] + $filters));
            $csv->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent());
        }
    }

    public function test_in_house_and_forecast_match_reservations(): void
    {
        $inHouse = DB::table('pms_reservations')->where('property_id', $this->property)->where('status', 'in_house')->count();
        $this->assertGreaterThan(0, $inHouse);
        $html = $this->actingAs($this->user)->get('/raporlar?r=in_house&goster=1')->assertOk()->getContent();
        $this->assertSame($inHouse, substr_count(explode('<tbody>', $html)[1] ?? '', '<tr>'));

        $date = '2026-12-01';
        $sold = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')->where('n.stay_date', $date)
            ->where('n.status', '<>', 'cancelled')->where('r.is_day_use', false)->whereIn('r.status', ['confirmed', 'in_house', 'checked_out'])->count();
        $csv = $this->actingAs($this->user)->get('/raporlar?r=forecast&format=csv&bas=01.12.2026&bit=01.12.2026')->streamedContent();
        $line = explode("\n", trim($csv))[1];
        $this->assertSame((string) $sold, explode(';', $line)[3]);
    }

    public function test_reports_need_group_permission(): void
    {
        $agent = User::create(['username' => 'HK1', 'name' => 'HK', 'email' => 'hk@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $agent->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'HKS')->value('id')]);
        $page = $this->actingAs($agent)->get('/raporlar')->assertOk();
        $page->assertSee('Free Rooms')->assertDontSee('Department Code Totals');
        $this->actingAs($agent)->get('/raporlar?r=tax&goster=1')->assertOk()->assertDontSee('data-report-table', false);   // yetkisiz rapor açılmaz

        $none = User::create(['username' => 'IFC', 'name' => 'IFC', 'email' => 'ifc@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $none->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'IFC')->value('id')]);
        $this->actingAs($none)->get('/raporlar')->assertForbidden();
    }

    public function test_night_audit_saves_reports_that_reopen_unchanged(): void
    {
        $bd = (string) DB::table('pms_properties')->where('id', $this->property)->value('current_business_date');
        $answers = NightAuditAnswers::class;
        $answers::save($this->property, array_fill_keys(array_keys($answers::get($this->property)), 'continue'), null);
        app(NightAuditService::class)->run($this->property, $this->user->id, array_fill_keys(array_keys($answers::get($this->property)), 'test'));
        $pack = json_decode(DB::table('pms_night_audit_runs')->where('business_date', $bd)->value('report_pack'), true);
        $this->assertSame(ReportCatalog::NIGHT_AUDIT_PACK, array_keys($pack['reports']));
        $roomNet = collect($pack['reports']['department_totals']['rows'])->firstWhere('code', '100')['net'] ?? 0;
        $this->assertGreaterThan(0, $roomNet);   // gece kapanışında oda ücretleri yazıldı

        $date = Carbon::parse($bd)->format('d.m.Y');
        $page = $this->actingAs($this->user)->get('/raporlar?r=department_totals&arsiv='.$date)->assertOk();
        $page->assertSee('data-archive-banner', false)->assertSee('data-report-table', false)->assertDontSee('Cancellations');   // ağaçta yalnız saklananlar

        // Sonradan yapılan düzeltme arşivi değiştirmez
        $any = DB::table('pms_postings')->where('business_date', $bd)->where('transaction_code_id', $this->code('100'))->first();
        DB::table('pms_postings')->insert(['property_id' => $this->property, 'business_date' => $bd, 'folio_id' => $any->folio_id, 'transaction_code_id' => $any->transaction_code_id,
            'ledger' => 'guest', 'kind' => 'adjustment', 'description' => 'Düzeltme', 'quantity' => 1, 'unit_price' => -1000, 'amount' => -1000, 'net_amount' => -1000, 'tax_amount' => 0,
            'source' => 'manual', 'created_at' => now()]);
        $this->assertStringNotContainsString(number_format($roomNet, 2, ',', ''), $this->actingAs($this->user)->get('/raporlar?r=department_totals&format=csv&bas='.Carbon::parse($bd)->format('d.m.Y').'&bit='.Carbon::parse($bd)->format('d.m.Y'))->streamedContent());
        $csv = $this->actingAs($this->user)->get('/raporlar?r=department_totals&format=csv&arsiv='.$date)->streamedContent();
        $this->assertStringContainsString(number_format($roomNet, 2, ',', ''), $csv);
        $this->actingAs($this->user)->get('/raporlar?r=department_totals&arsiv=01.01.2020')->assertSee('No completed night audit');
    }
}
