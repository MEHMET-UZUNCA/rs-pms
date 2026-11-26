<?php

namespace Tests\Feature\Http;

use App\Domain\Rates\Models\RateAmount;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\RateCalculator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class RateCodeScreenTest extends DomainTestCase
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

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function rate(string $code): RateCode
    {
        return RateCode::where('property_id', $this->property)->where('code', $code)->firstOrFail();
    }

    public function test_tree_with_2027_rates_and_summary(): void
    {
        $html = $this->actingAs($this->user)->get('/ayarlar/fiyat-kodlari')->assertOk()->getContent();
        foreach (['2027 FIYATLARI', '27COMP', '27PRO', '27AGY', '27IND', '27BOOKING', '27AGY/A', '27IND/A', 'Rack Rates', 'Agent Rates'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->actingAs($this->user)->get('/ayarlar/fiyat-kodlari/'.$this->rate('27COMP')->id.'/ozet')->assertOk()
            ->assertSee('Rate header information')->assertSee('COMPLIMENTARY')->assertSee('07.01.2025')->assertSee('No Board');
        $this->assertSame('comp', $this->rate('27COMP')->complimentary_type);
        $this->assertSame('2027-12-29', $this->rate('27IND/A')->valid_to->toDateString());
    }

    public function test_new_rate_with_header_fields_and_new_category(): void
    {
        $this->actingAs($this->user)->post('/ayarlar/fiyat-kodlari', [
            'code' => 'rhb', 'name' => 'RACK RATE HB', 'folio_text' => 'HB', 'new_category' => 'Rack 2028',
            'default_market_segment_id' => $this->id('pms_market_segments', 'IND'), 'valid_from' => '20.02.2027', 'valid_to' => '31.12.2028',
            'min_los' => '1', 'max_los' => '999', 'room_transaction_code_id' => $this->id('pms_transaction_codes', '100'),
            'weekend_transaction_code_id' => $this->id('pms_transaction_codes', '100'), 'package_id' => $this->id('pms_packages', 'HBD'),
            'currency_code' => 'USD', 'weekend' => ['4', '5'], 'refresh_exchange_at_checkin' => '1', 'is_public' => '1', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rate = $this->rate('RHB');
        $this->assertSame(['HB', 'USD', '2027-02-20', 999, 16 | 32, true], [$rate->folio_text, $rate->currency_code, $rate->valid_from->toDateString(),
            (int) $rate->max_los, (int) $rate->weekend_days, $rate->is_public]);
        $this->assertSame('Rack 2028', DB::table('pms_rate_categories')->where('id', $rate->rate_category_id)->value('name'));
        $this->assertSame('HBD', DB::table('pms_packages')->where('id', $rate->package_id)->value('code'));

        $this->actingAs($this->user)->post('/ayarlar/fiyat-kodlari', ['code' => 'RHB', 'name' => 'x', 'room_transaction_code_id' => $this->id('pms_transaction_codes', '100'), 'currency_code' => 'TRY'])
            ->assertSessionHasErrors('code');
        $this->actingAs($this->user)->get('/ayarlar/fiyat-kodlari?web=1')->assertSee('RHB')->assertDontSee('27COMP');
    }

    public function test_rate_detail_with_room_types_rooms_and_weekend_prices_is_used_by_calculator(): void
    {
        $rate = $this->rate('27IND');
        $rate->update(['weekend_days' => 16 | 32]);   // Cuma, Cumartesi
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", [
            'date_from' => '01.01.2027', 'date_to' => '31.12.2027', 'room_types' => [$this->id('pms_room_types', 'YMF'), $this->id('pms_room_types', 'YMT')],
            'days' => range(0, 6), 'price_default' => '700,00', 'price_1' => '620,00', 'price_2' => '640,00', 'weekend_price_2' => '800,00', 'child_band_1' => '0', 'child_band_2' => '100',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", [
            'date_from' => '01.01.2027', 'date_to' => '31.12.2027', 'room_numbers' => '1322, 2410', 'days' => range(0, 6), 'price_2' => '500',
        ])->assertSessionHasNoErrors();

        $detail = RateAmount::where('rate_code_id', $rate->id)->whereNull('room_numbers')->firstOrFail();
        $this->assertSame(['YMF', 'YMT'], $detail->roomTypes()->orderBy('code')->pluck('code')->all());

        $calc = app(RateCalculator::class);
        $ymf = $this->id('pms_room_types', 'YMF');
        $wed = CarbonImmutable::parse('2027-03-10');   // Çarşamba
        $fri = CarbonImmutable::parse('2027-03-12');   // Cuma (hafta sonu)
        $this->assertSame(620.0, $calc->quote($rate->fresh(), $ymf, $wed, 1)->amount);
        $this->assertSame(640.0, $calc->quote($rate->fresh(), $ymf, $wed, 2)->amount);
        $this->assertSame(800.0, $calc->quote($rate->fresh(), $ymf, $fri, 2)->amount);           // hafta sonu fiyatı
        $this->assertSame(700.0, $calc->quote($rate->fresh(), $ymf, $wed, 3)->amount);           // Default
        $this->assertSame(740.0, $calc->quote($rate->fresh(), $ymf, $wed, 2, [2 => 1])->amount); // CA2 100
        $this->assertFalse($calc->quote($rate->fresh(), $this->id('pms_room_types', 'ESU'), $wed, 2)->found());   // ESU listede yok
        $this->assertSame(500.0, $calc->quote($rate->fresh(), $this->id('pms_room_types', 'ESU'), $wed, 2, [], 0, 0, '2410')->amount);   // belirli oda
    }

    public function test_detail_validation_copy_delete_and_rate_copy(): void
    {
        $rate = $this->rate('27AGY');
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", ['date_from' => '01.01.2027', 'date_to' => '31.12.2027', 'days' => [0]])
            ->assertSessionHasErrors('price_default');
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", ['date_from' => '01.01.2027', 'date_to' => '31.12.2027', 'days' => [0], 'price_2' => '1', 'room_numbers' => '9999'])
            ->assertSessionHasErrors('room_numbers');
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", ['date_from' => '31.12.2027', 'date_to' => '01.01.2027', 'days' => [0], 'price_2' => '1'])
            ->assertSessionHasErrors('date_to');
        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/detay", ['date_from' => '01.01.2027', 'date_to' => '31.12.2027', 'days' => range(0, 6), 'price_2' => '450',
            'room_types' => [$this->id('pms_room_types', 'EMF')]])->assertSessionHasNoErrors();
        $detail = RateAmount::where('rate_code_id', $rate->id)->firstOrFail();
        $this->actingAs($this->user)->get("/ayarlar/fiyat-kodlari/{$rate->id}/detay/{$detail->id}/kopyala")->assertOk()->assertSee('value="450,00"', false);

        $this->actingAs($this->user)->post("/ayarlar/fiyat-kodlari/{$rate->id}/kopyala", ['code' => '28agy'])->assertRedirect();
        $copy = $this->rate('28AGY');
        $this->assertSame(1, $copy->amounts()->count());
        $this->assertSame(['EMF'], $copy->amounts()->first()->roomTypes()->pluck('code')->all());

        $this->actingAs($this->user)->delete("/ayarlar/fiyat-kodlari/{$rate->id}/detay/{$detail->id}")->assertRedirect();
        $this->assertSame(0, $rate->amounts()->count());
        $this->actingAs($this->user)->delete("/ayarlar/fiyat-kodlari/{$copy->id}")->assertRedirect('/ayarlar/fiyat-kodlari');
        $this->assertNull(RateCode::withTrashed()->find($copy->id));
        $this->actingAs($this->user)->delete('/ayarlar/fiyat-kodlari/'.$this->rate('BAR')->id)->assertSessionHas('error');   // rezervasyonda kullanılmış
    }

    public function test_permission(): void
    {
        $clerk = User::create(['username' => 'FOA1', 'name' => 'Clerk', 'email' => 'c@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($clerk)->get('/ayarlar/fiyat-kodlari')->assertForbidden();
    }
}
