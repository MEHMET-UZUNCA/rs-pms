<?php

namespace Tests\Feature\Domain;

use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\RateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RateCalculatorTest extends DomainTestCase
{
    private function amounts(int $rate, int $type, array $row): void
    {
        DB::table('pms_rate_amounts')->insert(array_merge(['rate_code_id' => $rate, 'room_type_id' => $type], $row));
    }

    public function test_occupancy_based_price_with_extra_adult_children_and_extra_bed(): void
    {
        $type = $this->roomType();
        $rate = $this->rateCode();
        $this->amounts($rate, $type, ['date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'price_1' => 3000, 'price_2' => 4000,
            'extra_adult' => 1500, 'child_band_1' => 0, 'child_band_2' => 750, 'extra_bed' => 500]);
        $calc = new RateCalculator;
        $code = RateCode::find($rate);
        $day = CarbonImmutable::parse('2026-12-10');

        $this->assertSame(3000.0, $calc->quote($code, $type, $day, 1)->amount);
        $this->assertSame(4000.0, $calc->quote($code, $type, $day, 2)->amount);
        $this->assertSame(5500.0, $calc->quote($code, $type, $day, 3)->amount);                 // 2 kişilik + 1 ek yetişkin
        $this->assertSame(4750.0, $calc->quote($code, $type, $day, 2, [1 => 1, 2 => 1])->amount); // bebek ücretsiz, çocuk 750
        $this->assertSame(4500.0, $calc->quote($code, $type, $day, 2, [], 1)->amount);           // ek yatak
        $this->assertFalse($calc->quote($code, $type, CarbonImmutable::parse('2027-01-05'), 2)->found());
    }

    public function test_narrowest_range_wins_and_weekday_mask_is_respected(): void
    {
        $type = $this->roomType();
        $rate = $this->rateCode();
        $this->amounts($rate, $type, ['date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'price_2' => 4000]);
        $this->amounts($rate, $type, ['date_from' => '2026-12-24', 'date_to' => '2026-12-26', 'price_2' => 7000]);     // yılbaşı dönemi
        $this->amounts($rate, $type, ['date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'weekdays' => 32 | 64, 'price_2' => 5000]); // Cmt+Paz
        $calc = new RateCalculator;
        $code = RateCode::find($rate);

        $this->assertSame(7000.0, $calc->quote($code, $type, CarbonImmutable::parse('2026-12-25'), 2)->amount);
        $this->assertSame(5000.0, $calc->quote($code, $type, CarbonImmutable::parse('2026-12-05'), 2)->amount); // Cumartesi
        $this->assertSame(4000.0, $calc->quote($code, $type, CarbonImmutable::parse('2026-12-07'), 2)->amount); // Pazartesi
    }

    public function test_derived_rate_code_and_board_package(): void
    {
        $type = $this->roomType();
        $hb = DB::table('pms_packages')->insertGetId(['property_id' => $this->property, 'code' => 'HB', 'name' => 'Yarım pansiyon']);
        DB::table('pms_package_elements')->insert([
            ['package_id' => $hb, 'transaction_code_id' => $this->code('110'), 'calc' => 'per_person', 'amount_adult' => 400, 'amount_child' => 200],
            ['package_id' => $hb, 'transaction_code_id' => $this->code('130'), 'calc' => 'per_person', 'amount_adult' => 900, 'amount_child' => 450],
        ]);
        $bar = $this->rateCode('BAR', ['package_id' => $hb]);
        $this->amounts($bar, $type, ['date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'price_2' => 6000]);
        $corp = $this->rateCode('CORP', ['parent_rate_code_id' => $bar, 'derive_type' => 'percent', 'derive_value' => -15, 'rounding' => '10', 'package_id' => $hb]);
        $calc = new RateCalculator;

        $quote = $calc->quote(RateCode::find($corp), $type, CarbonImmutable::parse('2026-12-10'), 2, [2 => 1]);
        $this->assertSame('derived', $quote->source);
        $this->assertSame(5100.0, $quote->amount);         // 6000 × 0,85 = 5100
        $this->assertSame(2600.0 + 650.0, $quote->packageAmount); // 2 yetişkin (400+900) + 1 çocuk (200+450)
    }
}
