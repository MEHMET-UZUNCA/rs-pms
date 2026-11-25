<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 8. aşama veritabanı: entegrasyon, kanal yöneticisi, kayıtlı raporlar, bildirimler, webhook (docs/03-veritabani.md M, P4, P7)
 * ve tasarımın tamamının oluştuğunun kontrolü.
 */
class StageEightSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_external_refs' => ['id', 'entity_type', 'entity_id', 'system', 'external_id', 'meta'],
        'pms_outbox' => ['id', 'event', 'payload', 'target', 'status', 'attempts', 'next_attempt_at', 'last_error'],
        'pms_guest_registrations' => ['id', 'property_id', 'reservation_id', 'reservation_guest_id', 'profile_id', 'room_id', 'type',
            'business_date', 'status', 'attempts', 'sent_at', 'response', 'error'],
        'pms_legacy_import_batches' => ['id', 'entity', 'source', 'parameters', 'started_at', 'finished_at', 'total', 'imported', 'skipped', 'failed', 'log_path', 'run_by'],
        'pms_legacy_import_errors' => ['id', 'batch_id', 'legacy_id', 'error', 'payload'],
        'pms_channel_connections' => ['id', 'property_id', 'provider', 'is_active', 'credentials', 'external_property_id', 'endpoint', 'webhook_secret', 'sync_days', 'last_sync_at'],
        'pms_channel_mappings' => ['id', 'channel_connection_id', 'room_type_id', 'rate_code_id', 'external_room_id', 'external_rate_id'],
        'pms_channel_ari_queue' => ['id', 'channel_connection_id', 'date_from', 'date_to', 'kinds', 'status', 'attempts', 'next_attempt_at', 'sent_at', 'sent_values', 'error'],
        'pms_saved_reports' => ['id', 'property_id', 'user_id', 'title', 'is_shared', 'dataset', 'columns', 'filters', 'group_by', 'sort', 'schedule', 'schedule_time', 'recipients'],
        'pms_notifications' => ['id', 'property_id', 'module', 'subject', 'notifiable_type', 'notifiable_id', 'route'],
        'pms_notification_reads' => ['notification_id', 'user_id', 'read_at'],
        'pms_webhooks' => ['id', 'property_id', 'label', 'url', 'events', 'secret', 'is_active', 'last_status', 'consecutive_failures'],
        'pms_webhook_deliveries' => ['id', 'webhook_id', 'event', 'event_id', 'status', 'attempts', 'response_code', 'duration_ms', 'payload'],
    ];

    public function test_stage_eight_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    /** Tasarım belgesindeki (docs/03-veritabani.md) her pms_ tablosu veritabanında olmalı. */
    public function test_every_designed_table_exists(): void
    {
        $doc = file_get_contents(base_path('docs/03-veritabani.md'));
        preg_match_all('/\b(pms_[a-z0-9_]*[a-z0-9])\b/', $doc, $m); // "pms_ar_*" gibi joker adlar dışarıda kalır
        $designed = array_values(array_unique($m[1]));
        $existing = array_map(fn ($t) => $t['name'], Schema::getTables());
        $missing = array_values(array_diff($designed, $existing));

        $this->assertGreaterThanOrEqual(211, count($designed), 'Tasarım belgesinden tablo listesi okunamadı');
        $this->assertSame([], $missing, 'Tasarımda olup veritabanında olmayan tablolar: '.implode(', ', $missing));
    }

    public function test_external_reference_is_unique_per_system(): void
    {
        DB::table('pms_external_refs')->insert(['entity_type' => 'profile', 'entity_id' => 1, 'system' => 'fidelio', 'external_id' => '123']);
        $this->expectException(QueryException::class);
        DB::table('pms_external_refs')->insert(['entity_type' => 'profile', 'entity_id' => 2, 'system' => 'fidelio', 'external_id' => '123']);
    }
}
