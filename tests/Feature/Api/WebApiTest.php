<?php

namespace Tests\Feature\Api;

use App\Domain\Core\Services\ExchangeRateService;
use App\Domain\Rates\Models\RateCode;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class WebApiTest extends DomainTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        Artisan::call('pms:api-client', ['name' => 'Web sitesi']);
        preg_match('/(rspms_\w+)/', Artisan::output(), $m);
        $this->token = $m[1];

        // 27IND online: 2027 tüm yıl YMF/YMT, 1 kişi 620, 2 kişi 640, CA2 100
        $rate = RateCode::where('code', '27IND')->firstOrFail();
        $rate->update(['is_public' => true, 'valid_from' => '2026-01-01', 'valid_to' => '2027-12-31', 'min_los' => 2]);
        $id = DB::table('pms_rate_amounts')->insertGetId(['rate_code_id' => $rate->id, 'date_from' => '2026-12-01', 'date_to' => '2027-12-31', 'weekdays' => 127,
            'price_1' => 620, 'price_2' => 640, 'child_band_2' => 100, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['YMF', 'YMT'] as $code) {
            DB::table('pms_rate_amount_room_types')->insert(['rate_amount_id' => $id, 'room_type_id' => DB::table('pms_room_types')->where('code', $code)->value('id')]);
        }
    }

    private function api(string $uri)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/api/v1'.$uri);
    }

    public function test_requires_valid_key(): void
    {
        $this->getJson('/api/v1/room-types')->assertUnauthorized()->assertJson(['error' => 'unauthorized']);
        $this->withHeader('Authorization', 'Bearer wrong')->getJson('/api/v1/room-types')->assertUnauthorized();
        $this->assertNotNull(DB::table('pms_api_clients')->value('token_hash'));
        $this->assertStringNotContainsString($this->token, (string) DB::table('pms_api_clients')->value('token_hash'));   // anahtar açık saklanmaz

        Artisan::call('pms:api-client', ['--revoke' => DB::table('pms_api_clients')->value('id')]);
        $this->api('/room-types')->assertUnauthorized();
    }

    public function test_room_types_and_online_rate_codes(): void
    {
        $this->api('/room-types')->assertOk()->assertJsonFragment(['code' => 'YMF'])->assertJsonPath('data.0.code', 'EMF');
        $codes = collect($this->api('/rate-codes')->assertOk()->json('data'))->pluck('code')->all();
        $this->assertSame(['27IND'], $codes);   // yalnız Online / Web işaretli
    }

    public function test_availability_with_prices_like_pms(): void
    {
        $res = $this->api('/availability?arrival=2026-12-10&departure=2026-12-13&adults=1&children=9')->assertOk();
        $res->assertJsonPath('nights', 3)->assertJsonPath('children', [9]);
        $types = collect($res->json('data'));
        $this->assertEqualsCanonicalizing(['YMT', 'YMF'], $types->pluck('room_type')->all());   // fiyatı olan tipler
        $ymf = $types->firstWhere('room_type', 'YMF');
        $this->assertGreaterThan(0, $ymf['available']);
        $this->assertSame('27IND', $ymf['rates'][0]['rate_code']);
        $this->assertEquals(3 * 720, $ymf['rates'][0]['total']);                // 1 yetişkin 620 + CA2 100
        $this->assertCount(3, $ymf['rates'][0]['nightly']);

        $this->assertSame([], $this->api('/availability?arrival=2026-12-10&departure=2026-12-13&adults=2&children=9')->json('data'));   // 3 kişi 2 kişilik odaya sığmaz
        $this->api('/availability?arrival=2026-12-10&departure=2026-12-11&adults=2')->assertOk()->assertJsonPath('data', []);   // en az 2 gece
        $this->api('/availability?arrival=2026-11-01&departure=2026-11-03&adults=2')->assertStatus(422);
        $this->api('/availability?arrival=2026-12-10&departure=2026-12-09&adults=2')->assertStatus(422);
    }

    public function test_foreign_currency_rate_returns_base_currency_total(): void
    {
        app(ExchangeRateService::class)->fetch($this->property, file_get_contents(base_path('tests/Fixtures/tcmb-today.xml')));
        RateCode::where('code', '27IND')->update(['currency_code' => 'EUR']);
        $rate = $this->api('/availability?arrival=2026-12-10&departure=2026-12-12&adults=1&room_type=YMF')->assertOk()->json('data.0.rates.0');
        $this->assertSame('EUR', $rate['currency']);
        $this->assertEquals(1240, $rate['total']);
        $this->assertEqualsWithDelta(1240 * 55.3967, $rate['total_base_currency'], 0.05);
        $this->assertSame('TRY', $rate['base_currency']);
    }
}
