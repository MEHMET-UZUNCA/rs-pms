<?php

namespace Tests\Feature\Http;

use App\Domain\Core\Services\ExchangeRateService;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Domain\DomainTestCase;

class ExchangeRateTest extends DomainTestCase
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

    private function xml(): string
    {
        return file_get_contents(base_path('tests/Fixtures/tcmb-today.xml'));
    }

    public function test_tcmb_xml_is_parsed_per_unit(): void
    {
        $rates = ExchangeRateService::parse($this->xml());
        $this->assertSame(50.6441, $rates['USD']['forex_sell']);
        $this->assertSame(50.5177, $rates['USD']['cash_buy']);
        $this->assertEqualsWithDelta(0.318607, $rates['JPY']['forex_sell'], 0.000001);   // TCMB: 100 JPY
    }

    public function test_kktc_style_xml_with_turkish_tags_is_parsed(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><KURLAR><Resmi_Kur><Sembol>EUR</Sembol><Birim>1</Birim><Doviz_Alis>55,1000</Doviz_Alis>'
            .'<Doviz_Satis>55,4000</Doviz_Satis><Efektif_Alis>55,0000</Efektif_Alis><Efektif_Satis>55,6000</Efektif_Satis></Resmi_Kur></KURLAR>';
        $this->assertSame(['forex_buy' => 55.1, 'forex_sell' => 55.4, 'cash_buy' => 55.0, 'cash_sell' => 55.6], ExchangeRateService::parse($xml)['EUR']);
    }

    public function test_fetch_applies_rocks_rules_like_fidelio(): void
    {
        $count = app(ExchangeRateService::class)->fetch($this->property, $this->xml());
        $this->assertSame(12, $count);
        $usd = app(ExchangeRateService::class)->current($this->property, 'USD');
        $this->assertSame('2026-12-01', $usd->business_date->toDateString());
        $this->assertEqualsWithDelta(50.6441, (float) $usd->posting_rate, 0.0001);       // Fidelio Rate 50,64
        $this->assertEqualsWithDelta(48.1119, (float) $usd->cash_rate, 0.0001);          // Fidelio Cash Rate 48,11 (−%5)
        $this->assertEqualsWithDelta(50.6441, (float) $usd->ratecode_rate, 0.0001);
        $this->assertSame('tcmb', $usd->source);

        // Kâr payı ve dayanak değişince yeni kur
        DB::table('pms_exchange_rate_rules')->where('currency_code', 'EUR')->update(['base' => 'forex_buy', 'rate_margin_pct' => 2]);
        app(ExchangeRateService::class)->fetch($this->property, $this->xml());
        $this->assertEqualsWithDelta(55.2971 * 1.02, (float) app(ExchangeRateService::class)->current($this->property, 'EUR')->posting_rate, 0.0001);
    }

    public function test_command_uses_source_url_and_screen_shows_rates(): void
    {
        Http::fake(['www.tcmb.gov.tr/*' => Http::response($this->xml())]);
        $this->artisan('pms:fetch-exchange-rates')->assertSuccessful();
        Http::assertSent(fn ($r) => $r->url() === 'https://www.tcmb.gov.tr/kurlar/today.xml');

        $this->actingAs($this->user)->get('/ayarlar/doviz-kurlari')->assertOk()
            ->assertSee('Exchange Rates')->assertSee('USD')->assertSee('50,6441')->assertSee('48,1119')->assertSee('TCMB (Central Bank of Turkey)');

        $this->actingAs($this->user)->put('/ayarlar/doviz-kurlari/kaynak', ['source' => 'kktcmb'])->assertSessionHas('status');
        $this->assertSame('kktcmb', ExchangeRateService::source($this->property));
        Http::fake(['mb.gov.ct.tr/*' => Http::response('', 503)]);
        $this->actingAs($this->user)->post('/ayarlar/doviz-kurlari/cek')->assertSessionHas('error');
    }

    public function test_rule_and_manual_rate_from_screen(): void
    {
        app(ExchangeRateService::class)->fetch($this->property, $this->xml());
        $this->actingAs($this->user)->get('/ayarlar/doviz-kurlari/GBP/duzenle')->assertOk()->assertSee('Hotel rate rule');

        $this->actingAs($this->user)->put('/ayarlar/doviz-kurlari/GBP', ['base' => 'forex_sell', 'rate_margin_pct' => '1', 'cash_margin_pct' => '-4', 'ratecode_margin_pct' => '0', 'is_auto' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $gbp = app(ExchangeRateService::class)->current($this->property, 'GBP');
        $this->assertEqualsWithDelta(66.3018 * 1.01, (float) $gbp->posting_rate, 0.0001);   // kural değişince bugünün kuru yeniden hesaplanır
        $this->assertEqualsWithDelta(66.3018 * 0.96, (float) $gbp->cash_rate, 0.0001);

        $this->actingAs($this->user)->put('/ayarlar/doviz-kurlari/GBP', ['base' => 'forex_sell', 'rate_margin_pct' => '1', 'cash_margin_pct' => '-4', 'ratecode_margin_pct' => '0', 'is_auto' => '0',
            'posting_rate' => '67,00', 'cash_rate' => '64'])->assertSessionHasNoErrors();
        $gbp = app(ExchangeRateService::class)->current($this->property, 'GBP');
        $this->assertSame(['67.00000000', '64.00000000', 'manual'], [(string) $gbp->posting_rate, (string) $gbp->cash_rate, $gbp->source]);

        app(ExchangeRateService::class)->fetch($this->property, $this->xml());   // otomatik kapalı: elle kur korunur
        $this->assertSame('67.00000000', (string) app(ExchangeRateService::class)->current($this->property, 'GBP')->posting_rate);

        $this->actingAs($this->user)->put('/ayarlar/doviz-kurlari/GBP', ['base' => 'x'])->assertSessionHasErrors('base');
    }
}
