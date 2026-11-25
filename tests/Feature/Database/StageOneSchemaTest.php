<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 1. aşama veritabanı: çekirdek, oda envanteri, kodlar, profiller (docs/03-veritabani.md A–D).
 */
class StageOneSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** Tablo => tasarımda zorunlu kolonlar */
    private const TABLES = [
        'users' => ['id', 'name', 'email', 'password', 'phone', 'locale', 'is_active', 'last_login_at', 'rsone_user_id'],
        // A. Çekirdek
        'pms_currencies' => ['code', 'short_code', 'name', 'decimals', 'is_active', 'sort', 'rate_period', 'legacy_id'],
        'pms_countries' => ['code', 'iso3', 'fidelio_code', 'name_tr', 'name_en', 'nationality_tr', 'kbs_code', 'phone_prefix', 'legacy_id'],
        'pms_properties' => ['id', 'code', 'name', 'legal_name', 'tax_office', 'tax_number', 'country_code', 'timezone', 'base_currency',
            'check_in_time', 'check_out_time', 'current_business_date', 'night_audit_time', 'exchange_diff_transaction_code_id',
            'rsone_business_id', 'is_active', 'deleted_at'],
        'pms_roles' => ['id', 'code', 'name'],
        'pms_permissions' => ['id', 'code', 'name', 'group'],
        'pms_role_permissions' => ['role_id', 'permission_id'],
        'pms_user_properties' => ['id', 'user_id', 'property_id', 'role_id', 'max_discount_pct', 'max_reversal_amount'],
        'pms_settings' => ['id', 'property_id', 'key', 'value', 'updated_by'],
        'pms_business_dates' => ['id', 'property_id', 'business_date', 'status', 'opened_at', 'closed_at', 'closed_by', 'night_audit_run_id'],
        'pms_exchange_rates' => ['id', 'property_id', 'currency_code', 'business_date', 'rate_type', 'buy_rate', 'sell_rate',
            'posting_rate', 'exchange_fee_pct', 'source'],
        'pms_taxes' => ['id', 'property_id', 'code', 'name', 'calc_type', 'rate', 'amount', 'is_inclusive', 'logo_tax_code', 'valid_from', 'valid_to'],
        'pms_number_sequences' => ['id', 'property_id', 'type', 'prefix', 'next_number', 'padding', 'reset_yearly', 'current_year'],
        'pms_reason_codes' => ['id', 'property_id', 'category', 'code', 'name', 'requires_note', 'is_active'],
        'pms_audit_logs' => ['id', 'property_id', 'user_id', 'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values',
            'reason_code_id', 'note', 'business_date', 'ip', 'user_agent', 'created_at'],
        // B. Oda envanteri
        'pms_buildings' => ['id', 'property_id', 'code', 'name', 'sort'],
        'pms_floors' => ['id', 'property_id', 'building_id', 'code', 'name', 'sort'],
        'pms_room_classes' => ['id', 'property_id', 'code', 'name', 'legacy_id'],
        'pms_room_types' => ['id', 'property_id', 'room_class_id', 'code', 'name', 'default_adults', 'max_adults', 'max_children',
            'max_occupancy', 'max_extra_beds', 'color', 'is_pseudo', 'beds', 'is_bookable_online', 'legacy_id'],
        'pms_rooms' => ['id', 'property_id', 'room_type_id', 'building_id', 'floor_id', 'hk_section_id', 'number', 'max_occupancy',
            'hk_credits', 'is_pseudo', 'fo_status', 'hk_status', 'service_status', 'current_reservation_id', 'hk_status_changed_at'],
        'pms_room_features' => ['id', 'property_id', 'code', 'name'],
        'pms_room_feature_room' => ['room_id', 'room_feature_id'],
        'pms_room_connections' => ['id', 'room_id', 'connected_room_id'],
        'pms_room_outages' => ['id', 'property_id', 'room_id', 'type', 'date_from', 'date_to', 'reason_code_id', 'return_hk_status',
            'rsone_task_id', 'released_at', 'released_by', 'created_by'],
        'pms_media' => ['id', 'property_id', 'mediable_type', 'mediable_id', 'path', 'category', 'caption', 'sort'],
        // C. Kodlar
        'pms_market_groups' => ['id', 'property_id', 'code', 'name'],
        'pms_market_segments' => ['id', 'property_id', 'market_group_id', 'code', 'name', 'is_active'],
        'pms_source_codes' => ['id', 'property_id', 'code', 'name'],
        'pms_channel_codes' => ['id', 'property_id', 'code', 'name', 'type'],
        'pms_reservation_types' => ['id', 'property_id', 'code', 'name', 'deducts_inventory', 'is_guaranteed', 'requires_deposit', 'release_hour'],
        'pms_vip_levels' => ['id', 'property_id', 'code', 'name', 'color'],
        'pms_special_requests' => ['id', 'property_id', 'category', 'code', 'name'],
        // D. Profiller
        'pms_profiles' => ['id', 'property_id', 'type', 'status', 'salutation', 'letter_greeting', 'first_name', 'middle_name', 'last_name',
            'company_name', 'display_name', 'gender', 'birth_date', 'father_name', 'mother_name', 'marital_status', 'occupation',
            'wedding_date', 'nationality_code', 'marketing_consent_source', 'photo_path', 'vip_level_id', 'tax_office', 'tax_number', 'iata_code', 'commission_code_id',
            'ar_account_id', 'default_rate_code_id', 'no_mailing', 'no_emailing', 'store_private_data', 'rsone_client_id',
            'merged_into_id', 'merged_at', 'primary_contact_profile_id', 'origin_hotel_name', 'origin_hotel_legacy_id',
            'legacy_system', 'legacy_id', 'legacy_global_id', 'legacy_created_by', 'legacy_updated_by'],
        'pms_profile_addresses' => ['id', 'profile_id', 'type', 'line1', 'line2', 'line3', 'city', 'state', 'postal_code', 'country_code', 'is_primary'],
        'pms_profile_communications' => ['id', 'profile_id', 'type', 'value', 'value_normalized', 'is_primary', 'marketing_allowed'],
        'pms_profile_identities' => ['id', 'profile_id', 'document_type', 'document_number', 'document_number_hash', 'serial_number',
            'issuing_country', 'registry', 'verified_at'],
        'pms_profile_relations' => ['id', 'profile_id', 'related_profile_id', 'relation'],
        'pms_profile_preferences' => ['id', 'profile_id', 'special_request_id', 'note'],
        'pms_membership_types' => ['id', 'property_id', 'code', 'name', 'role'],
        'pms_profile_memberships' => ['id', 'profile_id', 'membership_type_id', 'number', 'level', 'valid_until'],
        'pms_profile_negotiated_rates' => ['id', 'profile_id', 'property_id', 'rate_code_id', 'valid_from', 'valid_to'],
        'pms_note_categories' => ['id', 'property_id', 'code', 'name', 'area'],
        'pms_notes' => ['id', 'property_id', 'notable_type', 'notable_id', 'note_category_id', 'note', 'is_internal', 'show_on_check_in'],
        'pms_profile_stats' => ['profile_id', 'first_stay_date', 'last_stay_date', 'stays', 'nights', 'total_revenue', 'no_shows'],
    ];

    public function test_stage_one_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_core_records_can_be_linked(): void
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası', 'decimals' => 2]);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $propertyId = DB::table('pms_properties')->insertGetId([
            'code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY',
            'current_business_date' => '2026-11-24',
        ]);
        $classId = DB::table('pms_room_classes')->insertGetId(['property_id' => $propertyId, 'code' => 'STD', 'name' => 'Standart']);
        $typeId = DB::table('pms_room_types')->insertGetId(['property_id' => $propertyId, 'room_class_id' => $classId, 'code' => 'DBL', 'name' => 'Double']);
        DB::table('pms_rooms')->insert(['property_id' => $propertyId, 'room_type_id' => $typeId, 'number' => '101']);
        $profileId = DB::table('pms_profiles')->insertGetId([
            'property_id' => $propertyId, 'type' => 'individual', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz',
            'display_name' => 'Yılmaz, Ayşe', 'nationality_code' => 'CY',
        ]);
        DB::table('pms_profile_communications')->insert(['profile_id' => $profileId, 'type' => 'mobile', 'value' => '+90 533 000 00 00', 'value_normalized' => '+905330000000']);

        $this->assertSame('101', DB::table('pms_rooms')->where('room_type_id', $typeId)->value('number'));
        $this->assertSame(1, DB::table('pms_profile_communications')->where('profile_id', $profileId)->count());
        $this->assertSame('vacant', DB::table('pms_rooms')->value('fo_status'));
        $this->assertSame('active', DB::table('pms_profiles')->value('status'));
    }

    public function test_room_number_is_unique_per_property(): void
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $propertyId = DB::table('pms_properties')->insertGetId([
            'code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-24',
        ]);
        $typeId = DB::table('pms_room_types')->insertGetId(['property_id' => $propertyId, 'code' => 'DBL', 'name' => 'Double']);
        DB::table('pms_rooms')->insert(['property_id' => $propertyId, 'room_type_id' => $typeId, 'number' => '101']);

        $this->expectException(QueryException::class);
        DB::table('pms_rooms')->insert(['property_id' => $propertyId, 'room_type_id' => $typeId, 'number' => '101']);
    }

    public function test_migrations_can_be_rolled_back(): void
    {
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertFalse(Schema::hasTable('pms_profiles'));
        $this->assertFalse(Schema::hasTable('pms_properties'));
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('pms_profiles'));
    }
}
