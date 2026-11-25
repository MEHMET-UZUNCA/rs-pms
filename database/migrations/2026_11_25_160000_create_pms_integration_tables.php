<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M. Entegrasyon (dış kimlikler, RS ONE olay kuyruğu, kimlik bildirimi, Fidelio aktarım kayıtları),
 * P4 kanal yöneticisi, P7 (kayıtlı raporlar, bildirimler, webhook).
 * Tasarım: docs/03-veritabani.md bölüm M, P4, P7; Fidelio geçişi: docs/06-fidelio-gecis.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_external_refs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('system', 20);                      // rsone / fidelio / simphony / channel
            $table->string('external_id', 100);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['system', 'entity_type', 'external_id']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('pms_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);                       // reservation.checked_in / checked_out / room_moved / profile.updated
            $table->json('payload');
            $table->string('target', 20)->default('rsone');
            $table->string('status', 20)->default('pending');  // pending / sent / failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('pms_guest_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations');
            $table->foreignId('reservation_guest_id')->nullable()->constrained('pms_reservation_guests');
            $table->foreignId('profile_id')->constrained('pms_profiles');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->string('type', 20);                        // check_in / check_out / room_change
            $table->date('business_date');
            $table->string('status', 20)->default('pending');  // pending / sent / failed / not_required
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });

        Schema::create('pms_legacy_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 50);                      // profiles / reservations / postings ...
            $table->string('source', 20)->default('fidelio');
            $table->json('parameters')->nullable();            // tarih aralığı gibi parça bilgisi
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            foreach (['total', 'imported', 'skipped', 'failed'] as $count) {
                $table->unsignedInteger($count)->default(0);
            }
            $table->string('log_path', 500)->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('pms_legacy_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pms_legacy_import_batches')->cascadeOnDelete();
            $table->string('legacy_id', 50)->nullable();
            $table->text('error');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // P4. Kanal yöneticisi
        Schema::create('pms_channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('provider', 30);
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();           // şifreli JSON
            $table->string('external_property_id', 100)->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->string('webhook_secret', 255)->nullable();
            $table->unsignedSmallInteger('sync_days')->default(365);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_channel_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained('pms_channel_connections')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->string('external_room_id', 100);
            $table->string('external_rate_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_channel_ari_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained('pms_channel_connections')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('kinds');                             // availability / rate / restriction
            $table->string('status', 20)->default('queued');   // queued / sent / failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_values')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['status', 'next_attempt_at']);
        });

        // P7. Raporlama, bildirim, webhook
        Schema::create('pms_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->boolean('is_shared')->default(false);
            $table->string('dataset', 50);
            foreach (['columns', 'filters', 'group_by', 'sort'] as $json) {
                $table->json($json)->nullable();
            }
            $table->string('schedule', 10)->default('none');   // none / daily / weekly / monthly
            $table->time('schedule_time')->nullable();
            $table->json('recipients')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('module', 30);
            $table->string('subject', 255);
            $table->nullableMorphs('notifiable');
            $table->string('route', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['property_id', 'created_at']);
        });

        Schema::create('pms_notification_reads', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained('pms_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->primary(['notification_id', 'user_id']);
        });

        Schema::create('pms_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('label', 100);
            $table->string('url', 500);
            $table->json('events');
            $table->text('secret');                            // şifreli
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });

        Schema::create('pms_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('pms_webhooks')->cascadeOnDelete();
            $table->string('event', 60);
            $table->string('event_id', 64);
            $table->string('status', 20)->default('pending');  // pending / sent / failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['webhook_id', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_webhook_deliveries', 'pms_webhooks', 'pms_notification_reads', 'pms_notifications', 'pms_saved_reports',
            'pms_channel_ari_queue', 'pms_channel_mappings', 'pms_channel_connections', 'pms_legacy_import_errors',
            'pms_legacy_import_batches', 'pms_guest_registrations', 'pms_outbox', 'pms_external_refs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
