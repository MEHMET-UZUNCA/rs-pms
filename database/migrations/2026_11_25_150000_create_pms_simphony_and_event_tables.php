<?php

use App\Support\Database\FinancialRecordGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * K. Simphony (outlet'ler, arayüz, gelir/ödeme eşlemeleri, ham mesajlar, oda hesabı kayıtları, günlük satış)
 * O. Konferans, toplantı ve banket (Suite8 CCM) + P6 banket açık konuları.
 * Tasarım: docs/03-veritabani.md bölüm K, O, P6; Simphony: docs/04-simphony.md; outlet kodları: docs/fidelio/eslesme.md §15.
 * Önceki aşamalarda açılan outlet, POS kaydı ve etkinlik bağlantılarına kısıt eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- K. Simphony
        Schema::create('pms_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);                       // Cafe Rocks, Pool Bar, Room Service, Minibar, L'Hirondelle ...
            $table->string('type', 20)->default('restaurant'); // restaurant / bar / room_service / minibar / spa / shop / other
            $table->string('source_system', 20)->default('simphony'); // simphony / rsone / manual
            $table->unsignedInteger('simphony_rvc_number')->nullable();
            $table->unsignedBigInteger('rsone_department_id')->nullable()->index();
            $table->foreignId('default_transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'simphony_rvc_number']);
        });

        Schema::create('pms_pos_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('name', 100);
            $table->string('system', 20)->default('simphony');
            $table->string('protocol', 30)->default('simphony_pms_tcp'); // simphony_pms_tcp / fias / rest
            $table->string('listen_host', 100)->default('0.0.0.0');
            $table->unsignedInteger('listen_port');
            $table->json('allowed_ips')->nullable();
            $table->text('settings')->nullable();              // şifreli JSON
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_pos_revenue_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('outlet_id')->nullable()->constrained('pms_outlets');
            $table->string('source_type', 20);                 // sales_itemizer / major_group / family_group / service_charge / discount / tax / tip
            $table->string('source_code', 30);
            $table->string('source_name', 100)->nullable();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // 320 FOOD Cafe Rocks, 322 DISC FOOD ...
            $table->timestamps();
            $table->unique(['property_id', 'outlet_id', 'source_type', 'source_code'], 'pms_pos_revenue_mappings_unique');
        });

        Schema::create('pms_pos_tender_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('outlet_id')->nullable()->constrained('pms_outlets');
            $table->unsignedInteger('tender_number');
            $table->string('tender_name', 100);
            $table->string('kind', 20);                        // room_charge / cash / card / city_ledger / comp / other
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->timestamps();
            $table->unique(['property_id', 'outlet_id', 'tender_number'], 'pms_pos_tender_mappings_unique');
        });

        Schema::create('pms_pos_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('interface_id')->constrained('pms_pos_interfaces');
            $table->string('direction', 3);                    // in / out
            $table->string('message_type', 20);                // inquiry / posting / answer / link / heartbeat / error
            $table->longText('raw');
            $table->json('parsed')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('status', 20)->default('received'); // received / processed / failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['interface_id', 'created_at']);
        });

        Schema::create('pms_pos_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('interface_id')->constrained('pms_pos_interfaces');
            $table->foreignId('outlet_id')->nullable()->constrained('pms_outlets');
            $table->unsignedInteger('rvc_number');
            $table->string('check_number', 30);
            $table->string('workstation', 30)->nullable();
            $table->string('employee_number', 30)->nullable();
            $table->string('employee_name', 100)->nullable();
            $table->unsignedSmallInteger('covers')->nullable();
            $table->date('pos_business_date')->nullable();
            $table->date('business_date');
            $table->string('room_number', 10);
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('folio_id')->nullable()->constrained('pms_folios');
            $table->decimal('total_amount', 14, 2);
            $table->json('subtotals')->nullable();
            $table->decimal('tip_amount', 14, 2)->nullable();
            $table->string('status', 20);                      // accepted / rejected / reversed
            $table->string('reject_reason', 30)->nullable();   // room_not_found / not_in_house / no_post / credit_limit / closed_day
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['property_id', 'business_date']);
        });

        Schema::create('pms_pos_daily_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('outlet_id')->constrained('pms_outlets');
            $table->date('pos_business_date');
            $table->string('source', 20)->default('simphony_api'); // simphony_api / csv
            $table->unsignedInteger('checks_count')->default(0);
            $table->unsignedInteger('covers')->default(0);
            foreach (['net_sales', 'tax', 'service_charge', 'discounts'] as $amount) {
                $table->decimal($amount, 14, 2)->default(0);
            }
            $table->string('status', 20)->default('imported'); // imported / verified / exported
            $table->timestamp('imported_at')->useCurrent();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['outlet_id', 'pos_business_date']);
        });

        Schema::create('pms_pos_daily_sales_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_sales_id')->constrained('pms_pos_daily_sales')->cascadeOnDelete();
            $table->string('line_type', 20);                   // revenue / tender / tax / discount / service
            $table->string('source_code', 30);
            $table->string('source_name', 100)->nullable();
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->decimal('amount', 14, 2);
        });

        // ---- O. Konferans ve banket
        $simple = function (string $name, int $codeLength = 10, ?callable $extra = null) {
            Schema::create($name, function (Blueprint $table) use ($codeLength, $extra) {
                $table->id();
                $table->foreignId('property_id')->constrained('pms_properties');
                $table->string('code', $codeLength);
                $table->string('name', 100);
                if ($extra) {
                    $extra($table);
                }
                $table->timestamps();
                $table->unique(['property_id', 'code']);
            });
        };

        Schema::create('pms_function_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('pms_function_spaces');
            $table->boolean('is_combination')->default(false);
            $table->foreignId('floor_id')->nullable()->constrained('pms_floors');
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->decimal('height_m', 5, 2)->nullable();
            $table->boolean('has_daylight')->default(false);
            $table->char('color', 7)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        $simple('pms_seating_styles');
        Schema::create('pms_function_space_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_space_id')->constrained('pms_function_spaces')->cascadeOnDelete();
            $table->foreignId('seating_style_id')->constrained('pms_seating_styles');
            $table->unsignedSmallInteger('capacity_min')->default(0);
            $table->unsignedSmallInteger('capacity_max');
            $table->unique(['function_space_id', 'seating_style_id'], 'pms_function_space_capacities_unique');
        });
        Schema::create('pms_function_space_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_space_id')->constrained('pms_function_spaces')->cascadeOnDelete();
            $table->string('period', 10);                      // hour / half_day / full_day / evening
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });
        $simple('pms_event_attributes', 10, fn (Blueprint $t) => $t->string('category', 50)->nullable());
        Schema::create('pms_function_space_attributes', function (Blueprint $table) {
            $table->foreignId('function_space_id')->constrained('pms_function_spaces')->cascadeOnDelete();
            $table->foreignId('event_attribute_id')->constrained('pms_event_attributes')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->primary(['function_space_id', 'event_attribute_id']);
        });

        $simple('pms_event_types', 10, fn (Blueprint $t) => $t->string('event_group', 50)->nullable());
        $simple('pms_event_statuses', 10, function (Blueprint $t) {
            $t->boolean('blocks_space')->default(true);
            $t->boolean('is_final')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
        });
        $simple('pms_competitors');
        $simple('pms_menu_categories');
        Schema::create('pms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('menu_category_id')->nullable()->constrained('pms_menu_categories');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('person');     // person / piece / portion
            $table->decimal('price', 14, 2);
            $table->decimal('cost', 14, 2)->nullable();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });
        Schema::create('pms_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->decimal('price_per_person', 14, 2);
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });
        Schema::create('pms_menu_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('pms_menus')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('pms_menu_items');
            $table->unsignedSmallInteger('course')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
        });
        Schema::create('pms_event_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('quantity_total')->default(1);
            $table->decimal('price', 14, 2);
            $table->string('period', 10)->default('event');    // event / day / hour
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });
        Schema::create('pms_event_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->decimal('price_per_person', 14, 2);
            $table->char('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'code']);
        });
        Schema::create('pms_event_package_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_package_id')->constrained('pms_event_packages')->cascadeOnDelete();
            $table->string('element_type', 20);                // space / menu / menu_item / resource / room
            $table->unsignedBigInteger('element_id')->nullable();
            $table->decimal('quantity_per_person', 8, 2)->default(1);
            $table->decimal('amount', 14, 2);
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
        });

        Schema::create('pms_event_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('booking_number', 20);
            $table->string('name', 150);
            $table->foreignId('event_status_id')->constrained('pms_event_statuses');
            $table->foreignId('profile_id')->constrained('pms_profiles');
            $table->foreignId('agent_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('contact_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('block_id')->nullable()->constrained('pms_blocks');
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('attendees_expected')->default(0);
            $table->foreignId('market_segment_id')->nullable()->constrained('pms_market_segments');
            $table->foreignId('source_code_id')->nullable()->constrained('pms_source_codes');
            $table->foreignId('channel_code_id')->nullable()->constrained('pms_channel_codes');
            $table->char('currency_code', 3);
            $table->date('option_date')->nullable();
            $table->date('decision_date')->nullable();
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('pms_cancellation_policies');
            $table->foreignId('deposit_policy_id')->nullable()->constrained('pms_deposit_policies');
            $table->foreignId('master_folio_id')->nullable()->constrained('pms_folios');
            $table->foreignId('lost_to_competitor_id')->nullable()->constrained('pms_competitors');
            $table->foreignId('lost_reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->text('notes')->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'booking_number']);
            $table->index(['property_id', 'start_date']);
        });

        Schema::create('pms_event_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained('pms_event_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->decimal('total_amount', 14, 2);
            $table->char('currency_code', 3);
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft');    // draft / sent / accepted / rejected / expired
            $table->foreignId('document_id')->nullable()->constrained('pms_documents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['event_booking_id', 'revision']);
        });

        Schema::create('pms_event_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained('pms_event_bookings')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('pending');  // pending / paid / cancelled
            $table->foreignId('paid_posting_id')->nullable()->constrained('pms_postings');
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_event_open_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained('pms_event_bookings')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('detail')->nullable();
            $table->string('owner_side', 10)->default('hotel'); // hotel / client
            $table->date('due_date')->nullable();
            $table->decimal('price_impact', 14, 2)->nullable();
            $table->string('status', 10)->default('open');     // open / closed
            $table->timestamps();
        });

        Schema::create('pms_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained('pms_event_bookings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('event_type_id')->constrained('pms_event_types');
            $table->foreignId('event_status_id')->constrained('pms_event_statuses');
            $table->foreignId('function_space_id')->constrained('pms_function_spaces');
            $table->foreignId('seating_style_id')->nullable()->constrained('pms_seating_styles');
            $table->string('name', 150);
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedSmallInteger('setup_minutes')->default(0);
            $table->unsignedSmallInteger('teardown_minutes')->default(0);
            $table->unsignedSmallInteger('attendees_expected')->default(0);
            $table->unsignedSmallInteger('attendees_guaranteed')->nullable();
            $table->unsignedSmallInteger('attendees_actual')->nullable();
            $table->decimal('space_rate_amount', 14, 2)->nullable();
            $table->string('space_rate_period', 10)->nullable();
            $table->boolean('is_space_shareable')->default(false);
            $table->string('beo_number', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['function_space_id', 'start_at', 'end_at']);
        });

        Schema::create('pms_event_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('pms_events')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('pms_menus');
            $table->unsignedSmallInteger('covers');
            $table->decimal('price_per_person', 14, 2);
            $table->timestamp('serve_at')->nullable();
            $table->string('notes', 500)->nullable();
        });

        Schema::create('pms_event_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('pms_events')->cascadeOnDelete();
            $table->string('item_type', 20);                   // menu_item / resource / package / service / other
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->foreignId('posted_posting_id')->nullable()->constrained('pms_postings');
        });

        Schema::create('pms_event_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('pms_events')->cascadeOnDelete();
            $table->string('department', 20);                  // fnb / kitchen / technical / housekeeping / front_office / security
            $table->string('description', 500);
            $table->timestamp('due_at')->nullable();
            $table->string('status', 10)->default('open');     // open / done / cancelled
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
        });

        // ---- Önceki aşamalarda açılan bağlantılara kısıt
        Schema::table('pms_transaction_codes', fn (Blueprint $t) => $t->foreign('outlet_id')->references('id')->on('pms_outlets'));
        Schema::table('pms_package_elements', fn (Blueprint $t) => $t->foreign('outlet_id')->references('id')->on('pms_outlets'));
        Schema::table('pms_cashier_terminals', fn (Blueprint $t) => $t->foreign('outlet_id')->references('id')->on('pms_outlets'));
        Schema::table('pms_daily_revenue', fn (Blueprint $t) => $t->foreign('outlet_id')->references('id')->on('pms_outlets'));
        Schema::table('pms_folios', fn (Blueprint $t) => $t->foreign('event_booking_id')->references('id')->on('pms_event_bookings'));
        Schema::table('pms_checklist_templates', fn (Blueprint $t) => $t->foreign('event_type_id')->references('id')->on('pms_event_types'));
        Schema::table('pms_postings', function (Blueprint $t) {
            $t->foreign('outlet_id')->references('id')->on('pms_outlets');
            $t->foreign('pos_posting_id')->references('id')->on('pms_pos_postings');
            $t->foreign('event_id')->references('id')->on('pms_events');
        });

        // SQLite yabancı anahtar eklerken pms_postings tablosunu yeniden kurar ve tetikleyicileri siler: yeniden uygula.
        if (DB::getDriverName() === 'sqlite') {
            FinancialRecordGuard::protect('pms_postings', ['reversed_by_id', 'invoice_id']);
        }
    }

    public function down(): void
    {
        Schema::table('pms_postings', function (Blueprint $t) {
            $t->dropForeign(['outlet_id']);
            $t->dropForeign(['pos_posting_id']);
            $t->dropForeign(['event_id']);
        });
        if (DB::getDriverName() === 'sqlite') {
            FinancialRecordGuard::protect('pms_postings', ['reversed_by_id', 'invoice_id']);
        }
        Schema::table('pms_checklist_templates', fn (Blueprint $t) => $t->dropForeign(['event_type_id']));
        Schema::table('pms_folios', fn (Blueprint $t) => $t->dropForeign(['event_booking_id']));
        Schema::table('pms_daily_revenue', fn (Blueprint $t) => $t->dropForeign(['outlet_id']));
        Schema::table('pms_cashier_terminals', fn (Blueprint $t) => $t->dropForeign(['outlet_id']));
        Schema::table('pms_package_elements', fn (Blueprint $t) => $t->dropForeign(['outlet_id']));
        Schema::table('pms_transaction_codes', fn (Blueprint $t) => $t->dropForeign(['outlet_id']));
        foreach ([
            'pms_event_tasks', 'pms_event_items', 'pms_event_menus', 'pms_events', 'pms_event_open_items', 'pms_event_payment_schedules',
            'pms_event_quotes', 'pms_event_bookings', 'pms_event_package_elements', 'pms_event_packages', 'pms_event_resources',
            'pms_menu_menu_items', 'pms_menus', 'pms_menu_items', 'pms_menu_categories', 'pms_competitors', 'pms_event_statuses',
            'pms_event_types', 'pms_function_space_attributes', 'pms_event_attributes', 'pms_function_space_rates',
            'pms_function_space_capacities', 'pms_seating_styles', 'pms_function_spaces', 'pms_pos_daily_sales_lines',
            'pms_pos_daily_sales', 'pms_pos_postings', 'pms_pos_messages', 'pms_pos_tender_mappings', 'pms_pos_revenue_mappings',
            'pms_pos_interfaces', 'pms_outlets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
