<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 7. aşama veritabanı: Simphony ve konferans/banket (docs/03-veritabani.md K, O, P6).
 */
class StageSevenSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_outlets' => ['id', 'property_id', 'code', 'name', 'type', 'source_system', 'simphony_rvc_number', 'rsone_department_id', 'default_transaction_code_id'],
        'pms_pos_interfaces' => ['id', 'property_id', 'name', 'system', 'protocol', 'listen_host', 'listen_port', 'allowed_ips', 'settings', 'last_heartbeat_at'],
        'pms_pos_revenue_mappings' => ['id', 'property_id', 'outlet_id', 'source_type', 'source_code', 'source_name', 'transaction_code_id'],
        'pms_pos_tender_mappings' => ['id', 'property_id', 'outlet_id', 'tender_number', 'tender_name', 'kind', 'transaction_code_id'],
        'pms_pos_messages' => ['id', 'property_id', 'interface_id', 'direction', 'message_type', 'raw', 'parsed', 'correlation_id', 'status', 'error'],
        'pms_pos_postings' => ['id', 'property_id', 'interface_id', 'outlet_id', 'rvc_number', 'check_number', 'workstation', 'employee_number',
            'employee_name', 'covers', 'pos_business_date', 'business_date', 'room_number', 'reservation_id', 'folio_id', 'total_amount',
            'subtotals', 'tip_amount', 'status', 'reject_reason', 'idempotency_key'],
        'pms_pos_daily_sales' => ['id', 'property_id', 'outlet_id', 'pos_business_date', 'source', 'checks_count', 'covers', 'net_sales', 'tax',
            'service_charge', 'discounts', 'status'],
        'pms_pos_daily_sales_lines' => ['id', 'daily_sales_id', 'line_type', 'source_code', 'source_name', 'transaction_code_id', 'amount'],
        'pms_function_spaces' => ['id', 'property_id', 'code', 'name', 'parent_id', 'is_combination', 'floor_id', 'area_m2', 'height_m', 'has_daylight'],
        'pms_seating_styles' => ['id', 'property_id', 'code', 'name'],
        'pms_function_space_capacities' => ['id', 'function_space_id', 'seating_style_id', 'capacity_min', 'capacity_max'],
        'pms_function_space_rates' => ['id', 'function_space_id', 'period', 'amount', 'currency_code', 'transaction_code_id'],
        'pms_event_attributes' => ['id', 'property_id', 'code', 'name', 'category'],
        'pms_function_space_attributes' => ['function_space_id', 'event_attribute_id', 'quantity'],
        'pms_event_types' => ['id', 'property_id', 'code', 'name', 'event_group'],
        'pms_event_statuses' => ['id', 'property_id', 'code', 'name', 'blocks_space', 'is_final'],
        'pms_competitors' => ['id', 'property_id', 'code', 'name'],
        'pms_menu_categories' => ['id', 'property_id', 'code', 'name'],
        'pms_menu_items' => ['id', 'property_id', 'menu_category_id', 'code', 'name', 'unit', 'price', 'cost', 'transaction_code_id'],
        'pms_menus' => ['id', 'property_id', 'code', 'name', 'price_per_person', 'transaction_code_id'],
        'pms_menu_menu_items' => ['id', 'menu_id', 'menu_item_id', 'course', 'sort'],
        'pms_event_resources' => ['id', 'property_id', 'code', 'name', 'quantity_total', 'price', 'period', 'transaction_code_id'],
        'pms_event_packages' => ['id', 'property_id', 'code', 'name', 'price_per_person', 'currency_code'],
        'pms_event_package_elements' => ['id', 'event_package_id', 'element_type', 'element_id', 'quantity_per_person', 'amount', 'transaction_code_id'],
        'pms_event_bookings' => ['id', 'property_id', 'booking_number', 'name', 'event_status_id', 'profile_id', 'agent_profile_id',
            'contact_profile_id', 'block_id', 'sales_user_id', 'start_date', 'end_date', 'attendees_expected', 'currency_code', 'option_date',
            'decision_date', 'master_folio_id', 'lost_to_competitor_id', 'lost_reason_code_id'],
        'pms_event_quotes' => ['id', 'event_booking_id', 'revision', 'total_amount', 'currency_code', 'valid_until', 'status', 'document_id'],
        'pms_event_payment_schedules' => ['id', 'event_booking_id', 'due_date', 'amount', 'currency_code', 'status', 'paid_posting_id'],
        'pms_event_open_items' => ['id', 'event_booking_id', 'title', 'detail', 'owner_side', 'due_date', 'price_impact', 'status'],
        'pms_events' => ['id', 'event_booking_id', 'property_id', 'event_type_id', 'event_status_id', 'function_space_id', 'seating_style_id',
            'name', 'start_at', 'end_at', 'setup_minutes', 'teardown_minutes', 'attendees_expected', 'attendees_guaranteed',
            'attendees_actual', 'space_rate_amount', 'is_space_shareable', 'beo_number'],
        'pms_event_menus' => ['id', 'event_id', 'menu_id', 'covers', 'price_per_person', 'serve_at'],
        'pms_event_items' => ['id', 'event_id', 'item_type', 'item_id', 'description', 'quantity', 'unit_price', 'transaction_code_id', 'posted_posting_id'],
        'pms_event_tasks' => ['id', 'event_id', 'department', 'description', 'due_at', 'status', 'done_by', 'done_at'],
    ];

    public function test_stage_seven_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_simphony_message_is_processed_only_once(): void
    {
        [$p, $interface, $outlet] = $this->simphony();
        $row = ['property_id' => $p, 'interface_id' => $interface, 'outlet_id' => $outlet, 'rvc_number' => 3, 'check_number' => '1045',
            'business_date' => '2026-11-25', 'room_number' => '214', 'total_amount' => 1250, 'status' => 'accepted', 'idempotency_key' => 'rvc3-1045-1250'];
        DB::table('pms_pos_postings')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('pms_pos_postings')->insert($row);
    }

    public function test_outlet_revenue_mapping_per_food_and_discount(): void
    {
        [$p, , $outlet, $group] = $this->simphony();
        foreach ([['320', 'FOOD Cafe Rocks', 'food'], ['322', 'DISC FOOD Cafe Rocks', 'discount_food']] as [$code, $name, $source]) {
            $tc = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $group, 'code' => $code, 'name' => $name]);
            DB::table('pms_pos_revenue_mappings')->insert(['property_id' => $p, 'outlet_id' => $outlet, 'source_type' => 'sales_itemizer', 'source_code' => $source, 'transaction_code_id' => $tc]);
        }
        $this->assertSame(2, DB::table('pms_pos_revenue_mappings')->where('outlet_id', $outlet)->count());
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function simphony(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $p = DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
        $group = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'FNB', 'name' => 'Yiyecek İçecek']);
        $interface = DB::table('pms_pos_interfaces')->insertGetId(['property_id' => $p, 'name' => 'Simphony 18', 'listen_port' => 5010]);
        $outlet = DB::table('pms_outlets')->insertGetId(['property_id' => $p, 'code' => 'CAFE', 'name' => 'Cafe Rocks', 'simphony_rvc_number' => 3]);

        return [$p, $interface, $outlet, $group];
    }
}
