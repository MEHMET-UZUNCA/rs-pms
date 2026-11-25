<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 5. aşama veritabanı: gece kapanışı, istatistik, kat hizmetleri, servis talepleri, çamaşır, yönetim, kontrol listeleri
 * (docs/03-veritabani.md I, J, N7, N8, P5, P6).
 */
class StageFiveSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_night_audit_runs' => ['id', 'property_id', 'business_date', 'status', 'trigger', 'run_by', 'started_at', 'finished_at',
            'current_step', 'exceptions_count', 'report_pack', 'notes'],
        'pms_night_audit_steps' => ['id', 'run_id', 'code', 'sequence', 'status', 'started_at', 'finished_at', 'result', 'error'],
        'pms_night_audit_checks' => ['id', 'run_id', 'code', 'severity', 'items_count', 'items', 'acknowledged_by', 'acknowledged_reason'],
        'pms_daily_statistics' => ['id', 'property_id', 'business_date', 'dimension', 'dimension_key', 'rooms_available', 'rooms_ooo',
            'rooms_oos', 'rooms_sold', 'rooms_sold_tentative', 'rooms_comp', 'rooms_house_use', 'rooms_day_use', 'blocks_definite',
            'blocks_tentative', 'beds_available', 'beds_ooo', 'beds_oos', 'adults', 'children', 'arrivals', 'departures', 'no_shows',
            'cancellations', 'walk_ins', 'room_revenue', 'breakfast_revenue', 'lunch_revenue', 'dinner_revenue', 'allinc_food_revenue',
            'allinc_beverage_revenue', 'fnb_revenue', 'minibar_revenue', 'telephone_revenue', 'other_revenue', 'total_revenue',
            'room_revenue_gross', 'total_revenue_gross', 'occupancy_pct', 'adr', 'revpar'],
        'pms_daily_revenue' => ['id', 'property_id', 'business_date', 'transaction_code_id', 'outlet_id', 'postings_count', 'net_amount', 'tax_amount', 'gross_amount'],
        'pms_ledger_balances' => ['id', 'property_id', 'business_date', 'ledger', 'opening', 'debits', 'credits', 'closing'],
        'pms_hk_sections' => ['id', 'property_id', 'code', 'name', 'sort'],
        'pms_hk_attendants' => ['id', 'property_id', 'user_id', 'name', 'is_active'],
        'pms_hk_task_types' => ['id', 'property_id', 'code', 'name', 'minutes', 'credits', 'requires_inspection', 'same_day_turn_allowed'],
        'pms_hk_assignments' => ['id', 'property_id', 'business_date', 'attendant_id', 'room_id', 'task_type', 'hk_task_type_id', 'priority',
            'due_by', 'escalation_level', 'reject_reason', 'status', 'credits', 'started_at', 'finished_at', 'inspected_by', 'note', 'photo_path'],
        'pms_room_status_logs' => ['id', 'property_id', 'room_id', 'field', 'from_value', 'to_value', 'source', 'user_id', 'created_at'],
        'pms_room_discrepancies' => ['id', 'property_id', 'business_date', 'room_id', 'type', 'fo_status', 'hk_reported_status', 'fo_persons', 'hk_persons'],
        'pms_lost_found' => ['id', 'property_id', 'room_id', 'reservation_id', 'profile_id', 'found_at', 'found_by', 'description',
            'storage_location', 'status', 'returned_at', 'returned_to'],
        'pms_room_components' => ['id', 'suite_room_id', 'component_room_id'],
        'pms_service_requests' => ['id', 'property_id', 'room_id', 'reservation_id', 'category', 'description', 'priority', 'status',
            'sets_room_status', 'photo_path', 'rsone_task_id', 'reported_by', 'resolved_by', 'resolved_at'],
        'pms_service_request_events' => ['id', 'service_request_id', 'kind', 'from_value', 'to_value', 'note', 'user_id', 'created_at'],
        'pms_key_cards' => ['id', 'property_id', 'reservation_id', 'room_id', 'card_count', 'issued_at', 'valid_until', 'issued_by', 'encoder', 'cancelled_at'],
        'pms_virtual_numbers' => ['id', 'property_id', 'number', 'is_active'],
        'pms_virtual_number_assignments' => ['id', 'virtual_number_id', 'reservation_id', 'assigned_at', 'released_at', 'is_active'],
        'pms_laundry_rates' => ['id', 'property_id', 'item_name', 'service_type', 'rate', 'express_rate', 'transaction_code_id', 'is_active'],
        'pms_laundry_orders' => ['id', 'property_id', 'reservation_id', 'room_id', 'order_type', 'status', 'is_express', 'is_complimentary',
            'total', 'posted_posting_id', 'collected_by', 'collected_at', 'ready_at', 'delivered_by', 'delivered_at'],
        'pms_laundry_order_items' => ['id', 'laundry_order_id', 'laundry_rate_id', 'quantity', 'returned_quantity', 'rate', 'amount'],
        'pms_budgets' => ['id', 'property_id', 'date', 'metric', 'dimension', 'dimension_key', 'value'],
        'pms_manual_statistics' => ['id', 'property_id', 'business_date', 'code', 'name', 'value'],
        'pms_logbook_entries' => ['id', 'property_id', 'business_date', 'department', 'category', 'entry', 'is_important', 'read_by'],
        'pms_marketing_codes' => ['id', 'property_id', 'code', 'name', 'group'],
        'pms_profile_marketing_codes' => ['profile_id', 'marketing_code_id'],
        'pms_attachments' => ['id', 'property_id', 'attachable_type', 'attachable_id', 'file_path', 'file_name', 'mime', 'size'],
        'pms_checklist_templates' => ['id', 'property_id', 'key', 'name', 'department', 'event_type_id', 'is_active'],
        'pms_checklist_steps' => ['id', 'checklist_template_id', 'sequence', 'title', 'instruction', 'is_blocking', 'due_offset_days'],
    ];

    public function test_stage_five_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_one_night_audit_per_business_date(): void
    {
        $p = $this->property();
        DB::table('pms_night_audit_runs')->insert(['property_id' => $p, 'business_date' => '2026-11-25']);

        $this->expectException(QueryException::class);
        DB::table('pms_night_audit_runs')->insert(['property_id' => $p, 'business_date' => '2026-11-25']);
    }

    public function test_daily_statistics_by_dimension(): void
    {
        $p = $this->property();
        DB::table('pms_daily_statistics')->insert([
            ['property_id' => $p, 'business_date' => '2026-11-25', 'dimension' => 'total', 'dimension_key' => '', 'rooms_available' => 100, 'rooms_sold' => 72, 'occupancy_pct' => 72],
            ['property_id' => $p, 'business_date' => '2026-11-25', 'dimension' => 'room_type', 'dimension_key' => 'DBL', 'rooms_available' => 60, 'rooms_sold' => 45, 'occupancy_pct' => 75],
        ]);
        $this->assertSame(2, DB::table('pms_daily_statistics')->where('business_date', '2026-11-25')->count());

        $this->expectException(QueryException::class);
        DB::table('pms_daily_statistics')->insert(['property_id' => $p, 'business_date' => '2026-11-25', 'dimension' => 'total', 'dimension_key' => '']);
    }

    public function test_room_can_belong_to_a_housekeeping_section(): void
    {
        $p = $this->property();
        $section = DB::table('pms_hk_sections')->insertGetId(['property_id' => $p, 'code' => 'K1', 'name' => '1. kat']);
        $type = DB::table('pms_room_types')->insertGetId(['property_id' => $p, 'code' => 'DBL', 'name' => 'Double']);
        DB::table('pms_rooms')->insert(['property_id' => $p, 'room_type_id' => $type, 'number' => '101', 'hk_section_id' => $section]);

        $this->assertSame($section, (int) DB::table('pms_rooms')->where('number', '101')->value('hk_section_id'));
    }

    private function property(): int
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);

        return DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
    }
}
