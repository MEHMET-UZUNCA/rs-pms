<?php

namespace Tests\Feature\Domain;

use App\Domain\Core\Models\Tax;
use App\Domain\Core\Services\TaxCalculator;
use App\Domain\Core\Services\TaxRateService;
use App\Domain\Rates\Models\TransactionCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TaxRateTest extends DomainTestCase
{
    public function test_fidelio_tax_details_are_seeded_with_formula_rates(): void
    {
        $taxes = Tax::query()->where('property_id', $this->property)->orderBy('rate')->get();
        $this->assertSame([0.0, 1.0, 5.0, 10.0, 16.0, 20.0], $taxes->map(fn ($t) => (float) $t->rate)->all());
        $vat5 = $taxes->firstWhere('code', 'VAT5');
        $this->assertSame('VAT 18 %', $vat5->legacy_name);   // Fidelio'daki ad formülle uyuşmuyor; oran formülden
        $this->assertSame('VAT 5 %', $vat5->legacy_department);
        $this->assertTrue($taxes->firstWhere('code', 'VAT0')->post_zero);
        $this->assertTrue($vat5->is_inclusive);
    }

    public function test_import_links_department_codes_to_existing_fidelio_taxes(): void
    {
        $result = app(TaxRateService::class)->importFidelioRates($this->property, ['100' => 9.998, '320' => 20.01, '580' => 0, '999' => 10], CarbonImmutable::parse('2026-12-01'));

        $this->assertSame(['999'], $result['unknown_codes']);
        $this->assertSame(3, $result['linked']);
        $this->assertSame('VAT10', TransactionCode::find($this->code('100'))->taxes()->value('code'));
        $this->assertSame('VAT20', TransactionCode::find($this->code('320'))->taxes()->value('code'));
        $this->assertSame(6, Tax::query()->where('property_id', $this->property)->count()); // yeni vergi açılmadı
    }

    public function test_rate_change_applies_from_date_and_keeps_history(): void
    {
        $service = app(TaxRateService::class);
        $service->importFidelioRates($this->property, ['100' => 10], CarbonImmutable::parse('2026-12-01'));
        $vat10 = Tax::query()->where('property_id', $this->property)->where('code', 'VAT10')->first();

        $new = $service->changeRate($vat10, 12, CarbonImmutable::parse('2027-01-01'));

        $this->assertSame('2026-12-31', $vat10->fresh()->valid_to->toDateString());
        $this->assertSame('12.0000', $new->rate);
        $code = TransactionCode::find($this->code('100'));
        $calc = new TaxCalculator;
        $this->assertSame(1000.0, $calc->split($code, 1100, CarbonImmutable::parse('2026-12-31'))['net']); // eski oran %10
        $this->assertSame(1000.0, $calc->split($code, 1120, CarbonImmutable::parse('2027-01-02'))['net']); // yeni oran %12
    }

    public function test_change_rate_command(): void
    {
        app(TaxRateService::class)->importFidelioRates($this->property, ['100' => 10], CarbonImmutable::parse('2026-01-01'));
        $this->artisan('pms:change-tax-rate', ['code' => 'VAT10', 'rate' => '11', '--from' => '2027-02-01'])->assertSuccessful();
        $this->assertSame(2, DB::table('pms_taxes')->where('code', 'VAT10')->count());
    }
}
