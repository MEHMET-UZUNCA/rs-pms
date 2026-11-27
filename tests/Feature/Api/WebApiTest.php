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

    public function test_web_reservation_create_idempotent_status_and_cancel(): void
    {
        $post = fn (array $body) => $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson('/api/v1/reservations', $body);
        $body = ['external_reference' => 'W-1001', 'arrival' => '2027-03-10', 'departure' => '2027-03-13', 'adults' => 2, 'room_type' => 'YMF', 'rate_code' => '27IND',
            'guest' => ['last_name' => 'Weber', 'first_name' => 'Anna', 'email' => 'Anna@Example.com', 'phone' => '+49 151 000 000', 'nationality' => 'DE'], 'comments' => 'Sea view if possible', 'eta' => '18:30'];

        // Ayar yokken kapalı
        $post($body)->assertStatus(503);
        \App\Domain\Reservations\Support\ReservationOptions::save($this->property, ['web_reservation_type' => 'GTD', 'web_market' => 'IND', 'web_source' => 'DIR'], null);

        $res = $post($body)->assertCreated()->json('data');
        $this->assertSame(['confirmed', 'YMF', '27IND', 3, 'W-1001', 'Weber, Anna'], [$res['status'], $res['room_type'], $res['rate_code'], $res['nights'], $res['external_reference'], $res['guest']]);
        $this->assertEqualsWithDelta(3 * 640, $res['total'], 0.01);   // fiyat PMS'te hesaplanır
        $r = \App\Domain\Reservations\Models\Reservation::where('confirmation_number', $res['confirmation_number'])->firstOrFail();
        $this->assertSame(['WEB:W-1001', 'Sea view if possible', '18:30'], [$r->external_reference, $r->guest_comments, substr((string) $r->eta, 0, 5)]);
        $this->assertSame('anna@example.com', DB::table('pms_profile_communications')->where('profile_id', $r->profile_id)->where('type', 'email')->value('value_normalized'));

        // Aynı referans: aynı rezervasyon (tekrar oluşmaz); aynı e-posta: aynı profil
        $again = $post($body)->assertOk()->json();
        $this->assertTrue($again['duplicate']);
        $this->assertSame($res['confirmation_number'], $again['data']['confirmation_number']);
        $second = $post(array_merge($body, ['external_reference' => 'W-1002', 'arrival' => '2027-04-01', 'departure' => '2027-04-03']))->assertCreated()->json('data');
        $this->assertSame((int) $r->profile_id, (int) \App\Domain\Reservations\Models\Reservation::where('confirmation_number', $second['confirmation_number'])->value('profile_id'));

        // Kurallar
        $post(array_merge($body, ['external_reference' => 'X1', 'room_type' => 'ESU']))->assertStatus(409);   // fiyatı yok
        $post(array_merge($body, ['external_reference' => 'X2', 'rate_code' => 'TA-HB']))->assertStatus(422);   // online değil
        $post(array_merge($body, ['external_reference' => 'X3', 'arrival' => '2026-11-01', 'departure' => '2026-11-03']))->assertStatus(422);
        $post(['external_reference' => 'X4'])->assertStatus(422);

        // Durum ve iptal (soyadla)
        $get = fn (string $no, string $last) => $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson("/api/v1/reservations/{$no}?last_name={$last}");
        $get($res['confirmation_number'], 'weber')->assertOk()->assertJsonPath('data.status', 'confirmed');
        $get($res['confirmation_number'], 'other')->assertNotFound();
        $this->withHeader('Authorization', 'Bearer '.$this->token)->postJson("/api/v1/reservations/{$res['confirmation_number']}/cancel", ['last_name' => 'Weber'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertNotNull($r->fresh()->cancellation_number);

        // Ön büroda Web görünümü
        $user = \App\Models\User::create(['username' => 'ON', 'name' => 'Ön', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->withoutVite();
        $this->actingAs($user)->get('/rezervasyon?f=web')->assertOk()->assertSee('Weber, Anna');
    }
}
