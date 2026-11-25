<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 3. aşama veritabanı: rezervasyon ve rezervasyon hizmetleri (docs/03-veritabani.md F, N5, P2).
 */
class StageThreeSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_billing_instruction_codes' => ['id', 'property_id', 'code', 'name', 'text'],
        'pms_blocks' => ['id', 'property_id', 'code', 'name', 'type', 'status', 'profile_id', 'group_profile_id', 'start_date', 'end_date',
            'release_days', 'cutoff_date', 'rate_code_id', 'market_segment_id', 'source_code_id', 'deducts_inventory',
            'rooming_list_due', 'contract_number', 'master_reservation_id', 'attrition_pct', 'deposit_due_date', 'legacy_id'],
        'pms_block_inventory' => ['id', 'block_id', 'room_type_id', 'date', 'allotted', 'picked_up', 'released', 'rate_override'],
        'pms_bookings' => ['id', 'property_id', 'booking_number', 'name', 'booker_profile_id', 'booker_name', 'source_code_id', 'legacy_id'],
        'pms_reservations' => ['id', 'property_id', 'booking_id', 'confirmation_number', 'external_reference', 'status', 'reservation_type_id',
            'profile_id', 'company_profile_id', 'agent_profile_id', 'source_profile_id', 'group_profile_id', 'block_id', 'share_group_id',
            'booker_name', 'arrival_date', 'departure_date', 'original_departure_date', 'nights', 'sale_date', 'eta', 'etd',
            'checked_in_at', 'checked_out_at', 'checked_in_by', 'checked_out_by', 'room_assigned_by', 'room_assigned_at',
            'check_in_business_date', 'check_out_business_date', 'option_date', 'room_type_id', 'charge_room_type_id', 'room_id',
            'is_room_locked', 'adults', 'children', 'free_children', 'child_band_1', 'child_band_2', 'child_band_3', 'child_other',
            'infants', 'extra_beds', 'child_ages', 'rate_code_id', 'currency_code', 'is_rate_overridden', 'total_room_amount',
            'total_package_amount', 'is_complimentary', 'is_house_use', 'market_segment_id', 'source_code_id', 'channel_code_id',
            'vip_level_id', 'guarantee_type', 'payment_token_id', 'deposit_required', 'deposit_due_date', 'room_charges_allowed',
            'credit_limit', 'payment_instructions', 'pos_discount_code', 'billing_instruction_code_id', 'cancellation_policy_id',
            'cancelled_at', 'cancelled_by', 'cancel_reason_code_id', 'cancellation_number', 'no_show_at', 'waitlist_priority',
            'queued_at', 'is_walk_in', 'is_day_use', 'vehicle_plate', 'guest_comments', 'internal_notes', 'legacy_system',
            'legacy_id', 'legacy_created_by', 'legacy_updated_by'],
        'pms_reservation_nights' => ['id', 'reservation_id', 'property_id', 'stay_date', 'room_type_id', 'charge_room_type_id', 'room_id',
            'rate_code_id', 'adults', 'children', 'free_children', 'child_band_1', 'child_band_2', 'child_band_3', 'child_other',
            'amount', 'net_amount', 'package_amount', 'package_foreign_amount', 'package_included_amount', 'package_included_foreign_amount',
            'manual_amount', 'manual_foreign_amount', 'currency_code', 'exchange_rate', 'foreign_amount', 'foreign_net_amount',
            'is_complimentary', 'is_house_use', 'is_overridden', 'override_reason_code_id', 'market_segment_id', 'status',
            'posted_posting_id', 'legacy_id'],
        'pms_payment_tokens' => ['id', 'profile_id', 'reservation_id', 'provider', 'token', 'brand', 'last4', 'expiry_month', 'expiry_year'],
        'pms_reservation_guests' => ['id', 'reservation_id', 'profile_id', 'role', 'age', 'checked_in_at', 'checked_out_at'],
        'pms_reservation_packages' => ['id', 'reservation_id', 'package_id', 'quantity', 'date_from', 'date_to', 'amount_override'],
        'pms_reservation_status_logs' => ['id', 'reservation_id', 'from_status', 'to_status', 'reason_code_id', 'business_date', 'user_id'],
        'pms_reservation_room_moves' => ['id', 'reservation_id', 'from_room_id', 'to_room_id', 'business_date', 'reason_code_id', 'rate_changed'],
        'pms_fixed_charges' => ['id', 'reservation_id', 'transaction_code_id', 'amount', 'frequency', 'date_from', 'date_to', 'folio_window', 'last_posted_date'],
        'pms_deposit_requests' => ['id', 'reservation_id', 'due_date', 'amount', 'currency_code', 'status', 'paid_posting_id'],
        'pms_traces' => ['id', 'property_id', 'traceable_type', 'traceable_id', 'department', 'trace_date', 'trace_time', 'message', 'is_resolved'],
        'pms_alerts' => ['id', 'alertable_type', 'alertable_id', 'area', 'message', 'is_active'],
        'pms_reservation_night_promotions' => ['id', 'reservation_night_id', 'promotion_id', 'amount', 'foreign_amount'],
        'pms_reservation_rebates' => ['id', 'reservation_id', 'rebate_code_id', 'apply_from', 'used_count'],
        'pms_precheckins' => ['id', 'reservation_id', 'token', 'status', 'form_data', 'signature_path', 'privacy_consent', 'marketing_consent'],
        'pms_amenities' => ['id', 'property_id', 'code', 'name', 'transaction_code_id', 'price', 'department'],
        'pms_reservation_amenities' => ['id', 'reservation_id', 'amenity_id', 'deliver_date', 'cycle', 'quantity', 'status'],
        'pms_transport_types' => ['id', 'property_id', 'code', 'name', 'transaction_code_id', 'price'],
        'pms_reservation_transports' => ['id', 'reservation_id', 'direction', 'transport_type_id', 'pickup_at', 'location', 'flight_no', 'persons', 'status', 'posting_id'],
        'pms_items' => ['id', 'property_id', 'code', 'name', 'quantity_total', 'transaction_code_id', 'price'],
        'pms_reservation_items' => ['id', 'reservation_id', 'item_id', 'quantity', 'date_from', 'date_to'],
        'pms_guest_messages' => ['id', 'property_id', 'reservation_id', 'from_name', 'message', 'channel', 'received_at', 'delivered_at'],
        'pms_wakeup_calls' => ['id', 'property_id', 'reservation_id', 'room_id', 'call_at', 'status', 'source'],
        'pms_luggage' => ['id', 'property_id', 'reservation_id', 'profile_id', 'tag_number', 'pieces', 'location', 'stored_at', 'released_at'],
    ];

    public function test_stage_three_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_reservation_with_nightly_rows_and_guests(): void
    {
        $f = $this->fixture();
        $resId = $this->reservation($f, 'R-1001');
        foreach (['2026-12-01', '2026-12-02', '2026-12-03'] as $date) {
            DB::table('pms_reservation_nights')->insert([
                'reservation_id' => $resId, 'property_id' => $f['property'], 'stay_date' => $date, 'room_type_id' => $f['type'],
                'charge_room_type_id' => $f['type'], 'room_id' => $f['room'], 'rate_code_id' => $f['rate'], 'adults' => 2,
                'amount' => 4000, 'net_amount' => 3700, 'currency_code' => 'TRY', 'market_segment_id' => $f['segment'],
            ]);
        }
        $guest = DB::table('pms_profiles')->insertGetId(['type' => 'individual', 'display_name' => 'Örnek, Eş']);
        DB::table('pms_reservation_guests')->insert(['reservation_id' => $resId, 'profile_id' => $guest, 'role' => 'accompanying']);
        DB::table('pms_rooms')->where('id', $f['room'])->update(['current_reservation_id' => $resId, 'fo_status' => 'occupied']);

        $this->assertSame(3, DB::table('pms_reservation_nights')->where('reservation_id', $resId)->count());
        $this->assertSame('12000.00', number_format((float) DB::table('pms_reservation_nights')->where('reservation_id', $resId)->sum('amount'), 2, '.', ''));
        $this->assertSame($resId, (int) DB::table('pms_rooms')->where('id', $f['room'])->value('current_reservation_id'));
    }

    public function test_a_reservation_cannot_have_two_rows_for_the_same_night(): void
    {
        $f = $this->fixture();
        $resId = $this->reservation($f, 'R-1002');
        $night = ['reservation_id' => $resId, 'property_id' => $f['property'], 'stay_date' => '2026-12-01', 'room_type_id' => $f['type'],
            'charge_room_type_id' => $f['type'], 'rate_code_id' => $f['rate'], 'currency_code' => 'TRY', 'market_segment_id' => $f['segment']];
        DB::table('pms_reservation_nights')->insert($night);

        $this->expectException(QueryException::class);
        DB::table('pms_reservation_nights')->insert($night);
    }

    public function test_confirmation_number_is_unique_per_property(): void
    {
        $f = $this->fixture();
        $this->reservation($f, 'R-1003');

        $this->expectException(QueryException::class);
        $this->reservation($f, 'R-1003');
    }

    private function reservation(array $f, string $number): int
    {
        return DB::table('pms_reservations')->insertGetId([
            'property_id' => $f['property'], 'confirmation_number' => $number, 'reservation_type_id' => $f['resType'],
            'profile_id' => $f['profile'], 'arrival_date' => '2026-12-01', 'departure_date' => '2026-12-04',
            'original_departure_date' => '2026-12-04', 'nights' => 3, 'room_type_id' => $f['type'], 'charge_room_type_id' => $f['type'],
            'room_id' => $f['room'], 'adults' => 2, 'rate_code_id' => $f['rate'], 'currency_code' => 'TRY',
            'market_segment_id' => $f['segment'], 'source_code_id' => $f['source'],
        ]);
    }

    private function fixture(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $p = DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
        $type = DB::table('pms_room_types')->insertGetId(['property_id' => $p, 'code' => 'DBL', 'name' => 'Double']);
        $room = DB::table('pms_rooms')->insertGetId(['property_id' => $p, 'room_type_id' => $type, 'number' => '118']);
        $group = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'ROOM', 'name' => 'Konaklama']);
        $roomCode = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $group, 'code' => '100', 'name' => 'Room']);
        $rate = DB::table('pms_rate_codes')->insertGetId(['property_id' => $p, 'code' => 'BAR', 'name' => 'Raf', 'currency_code' => 'TRY', 'room_transaction_code_id' => $roomCode]);
        $segment = DB::table('pms_market_segments')->insertGetId(['property_id' => $p, 'code' => 'IND', 'name' => 'Bireysel']);
        $source = DB::table('pms_source_codes')->insertGetId(['property_id' => $p, 'code' => 'DIR', 'name' => 'Doğrudan']);
        $resType = DB::table('pms_reservation_types')->insertGetId(['property_id' => $p, 'code' => 'GTD', 'name' => 'Garantili']);
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $p, 'type' => 'individual', 'display_name' => 'Örnek, Misafir']);

        return ['property' => $p, 'type' => $type, 'room' => $room, 'rate' => $rate, 'segment' => $segment, 'source' => $source, 'resType' => $resType, 'profile' => $profile];
    }
}
