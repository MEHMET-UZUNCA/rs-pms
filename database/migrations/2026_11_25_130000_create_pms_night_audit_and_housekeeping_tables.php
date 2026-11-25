<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I. Gece kapanışı ve istatistik, J. Kat hizmetleri, N7 (suit bileşenleri, servis talepleri, kart anahtar, sanal dahili no),
 * N8 (bütçe, elle istatistik, vardiya defteri, pazarlama kodları, ek dosyalar), P5 (görev tipleri, çamaşır, servis talebi geçmişi),
 * P6 (kontrol listeleri).
 * Tasarım: docs/03-veritabani.md bölüm I, J, N7, N8, P5, P6.
 * Fidelio: PRO_ISV_RESERVATIONREVENUE (gelir kovaları), PRO_ISV_DAILY_ROOMBED_RMTYP, PRO_ISV_OOOROOMBEDS_ROOMTYPE, PRO_ISV_VIRTUALNUMBERS.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- I. Gece kapanışı ve istatistik
        Schema::create('pms_night_audit_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('status', 20)->default('running');   // running / completed / failed / held
            $table->string('trigger', 20)->default('manual');   // manual / scheduled
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('current_step', 40)->nullable();
            $table->unsignedSmallInteger('exceptions_count')->default(0);
            $table->longText('report_pack')->nullable();        // JSON — günün raporları, sonradan değişmez
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'business_date']);
        });

        Schema::create('pms_night_audit_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pms_night_audit_runs')->cascadeOnDelete();
            $table->string('code', 40);
            $table->unsignedTinyInteger('sequence');
            $table->string('status', 20)->default('pending');   // pending / running / done / failed / skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unique(['run_id', 'code']);
        });

        Schema::create('pms_night_audit_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pms_night_audit_runs')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('severity', 10);                     // block / warn / info
            $table->unsignedInteger('items_count')->default(0);
            $table->json('items')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acknowledged_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('pms_business_dates', function (Blueprint $table) {
            $table->foreign('night_audit_run_id')->references('id')->on('pms_night_audit_runs');
        });

        Schema::create('pms_daily_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('dimension', 20)->default('total');  // total / room_type / market_segment / source / rate_code / nationality / agent / company
            $table->string('dimension_key', 50)->default('');
            foreach (['rooms_available', 'rooms_ooo', 'rooms_oos', 'rooms_sold', 'rooms_sold_tentative', 'rooms_comp', 'rooms_house_use',
                'rooms_day_use', 'blocks_definite', 'blocks_tentative', 'beds_available', 'beds_ooo', 'beds_oos', 'adults', 'children',
                'arrivals', 'departures', 'no_shows', 'cancellations', 'walk_ins'] as $count) {
                $table->integer($count)->default(0);
            }
            foreach (['room_revenue', 'breakfast_revenue', 'lunch_revenue', 'dinner_revenue', 'allinc_food_revenue', 'allinc_beverage_revenue',
                'fnb_revenue', 'minibar_revenue', 'telephone_revenue', 'other_revenue', 'total_revenue', 'room_revenue_gross',
                'total_revenue_gross', 'adr', 'revpar'] as $amount) {
                $table->decimal($amount, 14, 2)->default(0);
            }
            $table->decimal('occupancy_pct', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'business_date', 'dimension', 'dimension_key'], 'pms_daily_statistics_unique');
        });

        Schema::create('pms_daily_revenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->unsignedBigInteger('outlet_id')->nullable()->index(); // FK: Simphony aşaması
            $table->unsignedInteger('postings_count')->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['property_id', 'business_date']);
        });

        Schema::create('pms_ledger_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('ledger', 10);                       // guest / deposit / ar / package
            foreach (['opening', 'debits', 'credits', 'closing'] as $amount) {
                $table->decimal($amount, 14, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['property_id', 'business_date', 'ledger']);
        });

        // ---- J. Kat hizmetleri + P5 görev tipleri
        Schema::create('pms_hk_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::table('pms_rooms', function (Blueprint $table) {
            $table->foreign('hk_section_id')->references('id')->on('pms_hk_sections');
        });

        Schema::create('pms_hk_attendants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_hk_task_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('minutes')->default(30);
            $table->decimal('credits', 4, 1)->default(1);
            $table->boolean('requires_inspection')->default(false);
            $table->boolean('same_day_turn_allowed')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_hk_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->foreignId('attendant_id')->nullable()->constrained('pms_hk_attendants');
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->string('task_type', 20);                    // departure / stayover / turndown / deep_clean / inspection
            $table->foreignId('hk_task_type_id')->nullable()->constrained('pms_hk_task_types');
            $table->string('priority', 10)->default('normal');  // low / normal / high / urgent
            $table->timestamp('due_by')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->string('reject_reason', 255)->nullable();
            $table->string('status', 20)->default('pending');   // pending / in_progress / done / inspected / skipped
            $table->decimal('credits', 4, 1)->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->timestamps();
            $table->index(['property_id', 'business_date', 'attendant_id']);
        });

        Schema::create('pms_room_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->string('field', 20);                        // hk_status / service_status / fo_status
            $table->string('from_value', 20)->nullable();
            $table->string('to_value', 20);
            $table->string('source', 20)->default('manual');    // manual / check_out / night_audit / mobile / outage
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['room_id', 'created_at']);
        });

        Schema::create('pms_room_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->string('type', 20);                         // skip / sleep / person_count
            $table->string('fo_status', 20);
            $table->string('hk_reported_status', 20);
            $table->unsignedTinyInteger('fo_persons')->nullable();
            $table->unsignedTinyInteger('hk_persons')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_lost_found', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->timestamp('found_at')->useCurrent();
            $table->foreignId('found_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 500);
            $table->string('storage_location', 100)->nullable();
            $table->string('status', 20)->default('stored');    // stored / returned / disposed
            $table->timestamp('returned_at')->nullable();
            $table->string('returned_to', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        // ---- N7. Oda ekleri
        Schema::create('pms_room_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_room_id')->constrained('pms_rooms')->cascadeOnDelete();
            $table->foreignId('component_room_id')->constrained('pms_rooms')->cascadeOnDelete();
            $table->unique(['suite_room_id', 'component_room_id']);
        });

        Schema::create('pms_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->string('category', 20);                     // maintenance / housekeeping / guest_request
            $table->string('description', 1000);
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');      // open / in_progress / resolved / cancelled
            $table->string('sets_room_status', 5)->nullable();  // ooo / oos
            $table->string('photo_path', 500)->nullable();
            $table->unsignedBigInteger('rsone_task_id')->nullable()->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });

        Schema::create('pms_service_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained('pms_service_requests')->cascadeOnDelete();
            $table->string('kind', 20);                         // created / assigned / status_changed / comment / escalated
            $table->string('from_value', 50)->nullable();
            $table->string('to_value', 50)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pms_key_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations');
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->unsignedTinyInteger('card_count')->default(1);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('encoder', 50)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::create('pms_virtual_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('number', 10);                       // F: VIRTUALNUMBER
            $table->boolean('is_active')->default(true);
            $table->unique(['property_id', 'number']);
        });

        Schema::create('pms_virtual_number_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_number_id')->constrained('pms_virtual_numbers');
            $table->foreignId('reservation_id')->constrained('pms_reservations');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->boolean('is_active')->default(true);        // F: ISINUSE
        });

        // ---- P5. Çamaşır
        Schema::create('pms_laundry_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('item_name', 100);
            $table->string('service_type', 20);                 // wash / dry_clean / iron
            $table->decimal('rate', 14, 2);
            $table->decimal('express_rate', 14, 2)->nullable();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // 510 Laundry
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->string('order_type', 10)->default('guest'); // guest / house
            $table->string('status', 20)->default('collected'); // collected / in_process / ready / delivered / cancelled
            $table->boolean('is_express')->default(false);
            $table->boolean('is_complimentary')->default(false);
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('posted_posting_id')->nullable()->constrained('pms_postings');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_laundry_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_order_id')->constrained('pms_laundry_orders')->cascadeOnDelete();
            $table->foreignId('laundry_rate_id')->constrained('pms_laundry_rates');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('returned_quantity')->default(0);
            $table->decimal('rate', 14, 2);
            $table->decimal('amount', 14, 2);
        });

        // ---- N8. Yönetim
        Schema::create('pms_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('date');
            $table->string('metric', 30);                       // rooms_sold / occupancy / adr / room_revenue / fnb_revenue ...
            $table->string('dimension', 20)->default('total');
            $table->string('dimension_key', 50)->default('');
            $table->decimal('value', 14, 2);
            $table->timestamps();
            $table->unique(['property_id', 'date', 'metric', 'dimension', 'dimension_key'], 'pms_budgets_unique');
        });

        Schema::create('pms_manual_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->decimal('value', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'business_date', 'code']);
        });

        Schema::create('pms_logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('department', 20);
            $table->string('category', 30)->nullable();
            $table->text('entry');
            $table->boolean('is_important')->default(false);
            $table->json('read_by')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['property_id', 'business_date', 'department']);
        });

        Schema::create('pms_marketing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('group', 50)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_profile_marketing_codes', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->foreignId('marketing_code_id')->constrained('pms_marketing_codes')->cascadeOnDelete();
            $table->primary(['profile_id', 'marketing_code_id']);
        });

        Schema::create('pms_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->morphs('attachable');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- P6. Kontrol listeleri
        Schema::create('pms_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('key', 30);                          // check_in / check_out / night_audit / vip_arrival / event_beo
            $table->string('name', 100);
            $table->string('department', 20)->nullable();
            $table->unsignedBigInteger('event_type_id')->nullable()->index(); // FK: banket aşaması
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_checklist_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained('pms_checklist_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('title', 200);
            $table->text('instruction')->nullable();
            $table->boolean('is_blocking')->default(false);
            $table->smallInteger('due_offset_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pms_rooms', function (Blueprint $table) {
            $table->dropForeign(['hk_section_id']);
        });
        Schema::table('pms_business_dates', function (Blueprint $table) {
            $table->dropForeign(['night_audit_run_id']);
        });
        foreach ([
            'pms_checklist_steps', 'pms_checklist_templates', 'pms_attachments', 'pms_profile_marketing_codes', 'pms_marketing_codes',
            'pms_logbook_entries', 'pms_manual_statistics', 'pms_budgets', 'pms_laundry_order_items', 'pms_laundry_orders',
            'pms_laundry_rates', 'pms_virtual_number_assignments', 'pms_virtual_numbers', 'pms_key_cards', 'pms_service_request_events',
            'pms_service_requests', 'pms_room_components', 'pms_lost_found', 'pms_room_discrepancies', 'pms_room_status_logs',
            'pms_hk_assignments', 'pms_hk_task_types', 'pms_hk_attendants', 'pms_hk_sections', 'pms_ledger_balances',
            'pms_daily_revenue', 'pms_daily_statistics', 'pms_night_audit_checks', 'pms_night_audit_steps', 'pms_night_audit_runs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
