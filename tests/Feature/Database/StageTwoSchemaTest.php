<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 2. aşama veritabanı: işlem kodları, fiyat ve müsaitlik (docs/03-veritabani.md E, G, N6, P3).
 */
class StageTwoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_transaction_code_groups' => ['id', 'property_id', 'parent_id', 'code', 'name', 'type'],
        'pms_transaction_codes' => ['id', 'property_id', 'group_id', 'code', 'name', 'type', 'payment_kind', 'currency_code',
            'revenue_bucket', 'food_beverage', 'is_discount', 'discount_of_transaction_code_id', 'is_no_show', 'is_tax_inclusive',
            'manual_post_allowed', 'outlet_id', 'legacy_id'],
        'pms_transaction_code_taxes' => ['id', 'transaction_code_id', 'tax_id', 'sequence', 'calc_base'],
        'pms_seasons' => ['id', 'property_id', 'code', 'name', 'color'],
        'pms_season_dates' => ['id', 'season_id', 'date_from', 'date_to'],
        'pms_rate_categories' => ['id', 'property_id', 'code', 'name'],
        'pms_day_types' => ['id', 'property_id', 'code', 'name', 'color'],
        'pms_day_type_calendar' => ['id', 'property_id', 'date', 'day_type_id'],
        'pms_child_age_bands' => ['id', 'property_id', 'band', 'age_from', 'age_to', 'name'],
        'pms_cancellation_policies' => ['id', 'property_id', 'code', 'name', 'description'],
        'pms_cancellation_policy_rules' => ['id', 'policy_id', 'days_before_arrival', 'hour', 'penalty_type', 'penalty_value', 'applies_to_no_show'],
        'pms_deposit_policies' => ['id', 'property_id', 'code', 'name'],
        'pms_deposit_policy_rules' => ['id', 'policy_id', 'due_type', 'due_days', 'amount_type', 'amount_value'],
        'pms_packages' => ['id', 'property_id', 'code', 'name', 'type', 'posting_rhythm', 'is_included_in_rate'],
        'pms_package_elements' => ['id', 'package_id', 'transaction_code_id', 'calc', 'amount_adult', 'amount_child', 'is_allowance', 'outlet_id'],
        'pms_rate_codes' => ['id', 'property_id', 'rate_category_id', 'code', 'name', 'currency_code', 'package_id', 'package_nights',
            'is_tax_inclusive', 'is_public', 'is_negotiated', 'parent_rate_code_id', 'derive_type', 'derive_value', 'rounding',
            'room_transaction_code_id', 'default_market_segment_id', 'default_source_code_id', 'cancellation_policy_id',
            'deposit_policy_id', 'min_los', 'max_los', 'valid_from', 'valid_to', 'legacy_id'],
        'pms_rate_code_room_types' => ['rate_code_id', 'room_type_id'],
        'pms_rate_amounts' => ['id', 'rate_code_id', 'room_type_id', 'day_type_id', 'date_from', 'date_to', 'weekdays', 'price_1',
            'price_2', 'price_3', 'price_4', 'extra_adult', 'child_band_1', 'child_band_2', 'child_band_3', 'extra_bed'],
        'pms_rate_restrictions' => ['id', 'property_id', 'rate_code_id', 'room_type_id', 'date', 'is_closed', 'closed_to_arrival',
            'closed_to_departure', 'min_los', 'max_los', 'min_advance_days', 'max_advance_days'],
        'pms_inventory_daily' => ['id', 'property_id', 'room_type_id', 'date', 'physical_rooms', 'out_of_order', 'out_of_service',
            'sold_definite', 'sold_tentative', 'block_remaining_definite', 'block_remaining_tentative', 'overbooking_limit', 'available'],
        'pms_overbooking_limits' => ['id', 'property_id', 'room_type_id', 'date', 'limit'],
        'pms_promotions' => ['id', 'property_id', 'code', 'name', 'promo_code', 'max_uses', 'times_used', 'rule_code', 'rule_name',
            'rule_type', 'value', 'stay_from', 'stay_to', 'booking_from', 'booking_to', 'min_nights'],
        'pms_rebate_codes' => ['id', 'property_id', 'code', 'name', 'barcode_prefix', 'legacy_id'],
        'pms_rebate_code_rules' => ['id', 'rebate_code_id', 'transaction_code_id', 'transaction_code_group_id', 'type', 'value',
            'max_count', 'discount_transaction_code_id'],
        'pms_rate_guardrails' => ['id', 'property_id', 'room_type_id', 'rate_code_id', 'floor_price', 'ceiling_price', 'weekdays'],
        'pms_occupancy_rate_rules' => ['id', 'property_id', 'room_type_id', 'occupancy_from_pct', 'premium_pct', 'min_rate'],
        'pms_turnaways' => ['id', 'property_id', 'business_date', 'arrival_date', 'nights', 'room_type_id', 'rate_code_id',
            'persons', 'reason_code_id', 'profile_id', 'source_code_id'],
    ];

    public function test_stage_two_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_fidelio_style_rate_setup_can_be_stored(): void
    {
        [$propertyId, $roomTypeId] = $this->property();

        $roomGroup = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $propertyId, 'code' => 'ROOM', 'name' => 'Konaklama']);
        $room = DB::table('pms_transaction_codes')->insertGetId([
            'property_id' => $propertyId, 'group_id' => $roomGroup, 'code' => '100', 'name' => 'Room', 'revenue_bucket' => 'room',
        ]);
        $breakfast = DB::table('pms_transaction_codes')->insertGetId([
            'property_id' => $propertyId, 'group_id' => $roomGroup, 'code' => '110', 'name' => 'Pension Breakfast', 'revenue_bucket' => 'breakfast',
        ]);
        $food = DB::table('pms_transaction_codes')->insertGetId([
            'property_id' => $propertyId, 'group_id' => $roomGroup, 'code' => '320', 'name' => 'FOOD Cafe Rocks', 'food_beverage' => 'food',
        ]);
        DB::table('pms_transaction_codes')->insert([
            'property_id' => $propertyId, 'group_id' => $roomGroup, 'code' => '322', 'name' => 'DISC FOOD Cafe Rocks',
            'is_discount' => true, 'discount_of_transaction_code_id' => $food,
        ]);

        $bb = DB::table('pms_packages')->insertGetId(['property_id' => $propertyId, 'code' => 'BB', 'name' => 'Oda Kahvaltı']);
        DB::table('pms_package_elements')->insert(['package_id' => $bb, 'transaction_code_id' => $breakfast, 'amount_adult' => 400, 'amount_child' => 200]);

        $bar = DB::table('pms_rate_codes')->insertGetId([
            'property_id' => $propertyId, 'code' => 'BAR', 'name' => 'Raf fiyatı', 'currency_code' => 'TRY',
            'package_id' => $bb, 'room_transaction_code_id' => $room,
        ]);
        DB::table('pms_rate_codes')->insert([
            'property_id' => $propertyId, 'code' => 'BAR-10', 'name' => 'Raf %10 indirimli', 'currency_code' => 'TRY',
            'parent_rate_code_id' => $bar, 'derive_type' => 'percent', 'derive_value' => -10, 'room_transaction_code_id' => $room,
        ]);
        DB::table('pms_rate_code_room_types')->insert(['rate_code_id' => $bar, 'room_type_id' => $roomTypeId]);
        DB::table('pms_rate_amounts')->insert([
            'rate_code_id' => $bar, 'room_type_id' => $roomTypeId, 'date_from' => '2026-12-01', 'date_to' => '2026-12-31',
            'price_1' => 3000, 'price_2' => 4000, 'extra_adult' => 1500, 'child_band_1' => 0, 'child_band_2' => 750,
        ]);

        $this->assertSame(2, DB::table('pms_rate_codes')->where('property_id', $propertyId)->count());
        $this->assertSame('BB', DB::table('pms_rate_codes')->join('pms_packages', 'pms_packages.id', '=', 'pms_rate_codes.package_id')
            ->where('pms_rate_codes.id', $bar)->value('pms_packages.code'));
        $this->assertSame(127, (int) DB::table('pms_rate_amounts')->value('weekdays'));
    }

    public function test_transaction_code_number_is_unique_per_property(): void
    {
        [$propertyId] = $this->property();
        $group = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $propertyId, 'code' => 'PAY', 'name' => 'Ödemeler', 'type' => 'payment']);
        DB::table('pms_transaction_codes')->insert(['property_id' => $propertyId, 'group_id' => $group, 'code' => '900', 'name' => 'Cash', 'type' => 'payment']);

        $this->expectException(QueryException::class);
        DB::table('pms_transaction_codes')->insert(['property_id' => $propertyId, 'group_id' => $group, 'code' => '900', 'name' => 'Cash 2', 'type' => 'payment']);
    }

    public function test_inventory_is_unique_per_room_type_and_day(): void
    {
        [$propertyId, $roomTypeId] = $this->property();
        DB::table('pms_inventory_daily')->insert(['property_id' => $propertyId, 'room_type_id' => $roomTypeId, 'date' => '2026-12-01', 'physical_rooms' => 10, 'available' => 10]);

        $this->expectException(QueryException::class);
        DB::table('pms_inventory_daily')->insert(['property_id' => $propertyId, 'room_type_id' => $roomTypeId, 'date' => '2026-12-01', 'physical_rooms' => 10]);
    }

    /** @return array{0:int,1:int} */
    private function property(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $propertyId = DB::table('pms_properties')->insertGetId([
            'code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25',
        ]);
        $roomTypeId = DB::table('pms_room_types')->insertGetId(['property_id' => $propertyId, 'code' => 'DBL', 'name' => 'Double']);

        return [$propertyId, $roomTypeId];
    }
}
