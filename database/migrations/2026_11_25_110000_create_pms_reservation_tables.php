<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F. Rezervasyon — blok/allotman, çok odalı rezervasyon, rezervasyon (oda başına), gece satırları,
 * ek misafirler, paketler, durum ve oda değişikliği geçmişi, sabit ücretler, depozito talepleri,
 * kart anahtarları, izler, uyarılar, gece promosyonları, indirim (rebate) bağlantıları, çevrimiçi giriş.
 * Tasarım: docs/03-veritabani.md bölüm F, N1 (fatura talimatı), P2. Fidelio: PRO_ISV_RESERVATIONINFO, PRO_ISV_MULTIGUEST_LIST, PRO_ISV_PROMOTIONS.
 * Folyo kayıtlarına giden alanlar (posting) kasa aşamasında kısıt alır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_billing_instruction_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('text', 500)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 20);                       // F: BLOCK_NAME
            $table->string('name', 150);
            $table->string('type', 10)->default('allotment'); // allotment / group
            $table->string('status', 20)->default('tentative'); // inquiry / tentative / definite / cancelled
            $table->foreignId('profile_id')->constrained('pms_profiles');           // acente / şirket
            $table->foreignId('group_profile_id')->nullable()->constrained('pms_profiles');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('release_days')->default(0);
            $table->date('cutoff_date')->nullable();
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->foreignId('market_segment_id')->nullable()->constrained('pms_market_segments');
            $table->foreignId('source_code_id')->nullable()->constrained('pms_source_codes');
            $table->boolean('deducts_inventory')->default(true);
            $table->date('rooming_list_due')->nullable();
            $table->string('contract_number', 50)->nullable();
            $table->unsignedBigInteger('master_reservation_id')->nullable()->index(); // kısıt aşağıda
            $table->decimal('attrition_pct', 5, 2)->nullable();
            $table->date('deposit_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('legacy_id', 50)->nullable();      // F: BLOCK_ID
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_block_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('pms_blocks')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->date('date');
            $table->unsignedSmallInteger('allotted')->default(0);
            $table->unsignedSmallInteger('picked_up')->default(0);
            $table->unsignedSmallInteger('released')->default(0);
            $table->decimal('rate_override', 14, 2)->nullable();
            $table->unique(['block_id', 'room_type_id', 'date']);
        });

        Schema::create('pms_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('booking_number', 20);
            $table->string('name', 60)->nullable();           // F: PARTY_LONGDESC
            $table->foreignId('booker_profile_id')->nullable()->constrained('pms_profiles');
            $table->string('booker_name', 150)->nullable();
            $table->string('booker_phone', 40)->nullable();
            $table->string('booker_email', 190)->nullable();
            $table->foreignId('source_code_id')->nullable()->constrained('pms_source_codes');
            $table->foreignId('channel_code_id')->nullable()->constrained('pms_channel_codes');
            $table->string('external_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('legacy_id', 50)->nullable();      // F: PARTY_ID
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'booking_number']);
        });

        Schema::create('pms_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('booking_id')->nullable()->constrained('pms_bookings');
            $table->string('confirmation_number', 20);
            $table->string('external_reference', 100)->nullable(); // F: CRSRESNR
            $table->string('status', 20)->default('confirmed');     // inquiry / tentative / confirmed / waitlist / in_house / checked_out / cancelled / no_show
            $table->foreignId('reservation_type_id')->constrained('pms_reservation_types'); // F: GUARANTEECODE
            // Kişiler
            $table->foreignId('profile_id')->constrained('pms_profiles');
            $table->foreignId('company_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('agent_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('source_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('group_profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('block_id')->nullable()->constrained('pms_blocks');
            $table->unsignedBigInteger('share_group_id')->nullable()->index(); // F: SHARENUMBER
            $table->string('booker_name', 150)->nullable();                    // F: BOOKER
            // Tarih
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->date('original_departure_date');                           // F: ORIGINAL_DEPARTUREDATE
            $table->unsignedSmallInteger('nights');
            $table->date('sale_date')->nullable();                             // F: SALEDATE
            $table->time('eta')->nullable();
            $table->time('etd')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('room_assigned_at')->nullable();
            $table->date('check_in_business_date')->nullable();
            $table->date('check_out_business_date')->nullable();
            $table->date('option_date')->nullable();
            // Oda
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->foreignId('charge_room_type_id')->constrained('pms_room_types');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->boolean('is_room_locked')->default(false);
            foreach (['adults', 'children', 'free_children', 'child_band_1', 'child_band_2', 'child_band_3', 'child_other', 'infants', 'extra_beds'] as $count) {
                $table->unsignedTinyInteger($count)->default($count === 'adults' ? 1 : 0);
            }
            $table->json('child_ages')->nullable();
            // Fiyat
            $table->foreignId('rate_code_id')->constrained('pms_rate_codes');
            $table->char('currency_code', 3);
            $table->boolean('is_rate_overridden')->default(false);
            $table->decimal('total_room_amount', 14, 2)->default(0);
            $table->decimal('total_package_amount', 14, 2)->default(0);
            $table->boolean('is_complimentary')->default(false);
            $table->boolean('is_house_use')->default(false);
            // Pazar
            $table->foreignId('market_segment_id')->constrained('pms_market_segments');
            $table->foreignId('source_code_id')->constrained('pms_source_codes');
            $table->foreignId('channel_code_id')->nullable()->constrained('pms_channel_codes');
            $table->foreignId('vip_level_id')->nullable()->constrained('pms_vip_levels');
            // Garanti / ödeme
            $table->string('guarantee_type', 20)->default('none'); // none / card / deposit / company / agent
            $table->unsignedBigInteger('payment_token_id')->nullable()->index(); // kısıt aşağıda
            $table->decimal('deposit_required', 14, 2)->default(0);
            $table->date('deposit_due_date')->nullable();
            $table->boolean('room_charges_allowed')->default(true);
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->string('payment_instructions', 500)->nullable(); // F: BILLINGINFO
            $table->string('pos_discount_code', 30)->nullable();     // F: POSDISCOUNT
            $table->foreignId('billing_instruction_code_id')->nullable()->constrained('pms_billing_instruction_codes');
            // İptal / no-show / bekleme
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('pms_cancellation_policies');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancel_reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->string('cancellation_number', 20)->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->unsignedSmallInteger('waitlist_priority')->nullable();
            $table->timestamp('queued_at')->nullable();
            // Diğer
            $table->boolean('is_walk_in')->default(false);
            $table->boolean('is_day_use')->default(false);
            $table->string('vehicle_plate', 30)->nullable();          // F: ARACPLAKANO
            $table->text('guest_comments')->nullable();               // F: REMARKS
            $table->text('internal_notes')->nullable();
            $table->string('legacy_system', 20)->nullable();
            $table->string('legacy_id', 50)->nullable();              // F: RESERVATION_ID
            $table->string('legacy_created_by', 100)->nullable();
            $table->string('legacy_updated_by', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'confirmation_number']);
            $table->index(['property_id', 'arrival_date']);
            $table->index(['property_id', 'departure_date']);
            $table->index(['property_id', 'status']);
            $table->index(['room_id', 'arrival_date']);
            $table->index(['legacy_system', 'legacy_id']);
        });

        Schema::create('pms_reservation_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('stay_date');                                  // F: DETAIL_DATE
            $table->foreignId('room_type_id')->constrained('pms_room_types');        // F: ROOMTYPE
            $table->foreignId('charge_room_type_id')->constrained('pms_room_types'); // F: RATEROOMTYPE
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');      // F: ROOMNUMBER
            $table->foreignId('rate_code_id')->constrained('pms_rate_codes');        // F: RATECODE
            foreach (['adults', 'children', 'free_children', 'child_band_1', 'child_band_2', 'child_band_3', 'child_other'] as $count) {
                $table->unsignedTinyInteger($count)->default($count === 'adults' ? 1 : 0);
            }
            $table->decimal('amount', 14, 2)->default(0);               // F: RATECODEAMOUNT (brüt)
            $table->decimal('net_amount', 14, 2)->default(0);           // F: NETRATECODEAMOUNT
            $table->decimal('package_amount', 14, 2)->default(0);       // F: PACKAGEAMOUNT
            $table->decimal('package_foreign_amount', 14, 2)->nullable();
            $table->decimal('package_included_amount', 14, 2)->default(0); // F: PACKAGEAMOUNT_INCLUDED
            $table->decimal('package_included_foreign_amount', 14, 2)->nullable();
            $table->decimal('manual_amount', 14, 2)->nullable();        // F: RATEMANUAL
            $table->decimal('manual_foreign_amount', 14, 2)->nullable();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('foreign_amount', 14, 2)->nullable();       // F: FOREIGNRATECODEAMOUNT
            $table->decimal('foreign_net_amount', 14, 2)->nullable();
            $table->boolean('is_complimentary')->default(false);        // F: CH = 1
            $table->boolean('is_house_use')->default(false);            // F: CH = 2
            $table->boolean('is_overridden')->default(false);
            $table->foreignId('override_reason_code_id')->nullable()->constrained('pms_reason_codes'); // F: RATEDIFFREASON
            $table->foreignId('market_segment_id')->constrained('pms_market_segments');
            $table->string('status', 20)->default('expected');          // expected / posted / cancelled
            $table->unsignedBigInteger('posted_posting_id')->nullable()->index(); // FK: kasa aşaması
            $table->string('legacy_id', 50)->nullable();                // F: DETAIL_ID
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['reservation_id', 'stay_date']);
            $table->index(['property_id', 'stay_date']);
        });

        Schema::create('pms_payment_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->string('provider', 30);
            $table->text('token');                            // şifreli — kart numarası ve CVV ASLA saklanmaz
            $table->string('brand', 20)->nullable();
            $table->char('last4', 4)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->string('holder_name', 150)->nullable();
            $table->timestamps();
        });

        Schema::table('pms_reservations', function (Blueprint $table) {
            $table->foreign('payment_token_id')->references('id')->on('pms_payment_tokens');
        });
        Schema::table('pms_blocks', function (Blueprint $table) {
            $table->foreign('master_reservation_id')->references('id')->on('pms_reservations');
        });
        Schema::table('pms_rooms', function (Blueprint $table) {
            $table->foreign('current_reservation_id')->references('id')->on('pms_reservations');
        });

        Schema::create('pms_reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('pms_profiles');
            $table->string('role', 20)->default('accompanying'); // primary / sharer / accompanying / child — F: MULTIGUEST
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->unique(['reservation_id', 'profile_id']);
        });

        Schema::create('pms_reservation_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('pms_packages');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('amount_override', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_reservation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->string('note', 500)->nullable();
            $table->date('business_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pms_reservation_room_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('from_room_id')->nullable()->constrained('pms_rooms');
            $table->foreignId('to_room_id')->constrained('pms_rooms');
            $table->date('business_date');
            $table->foreignId('reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->boolean('rate_changed')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pms_fixed_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('amount', 14, 2);
            $table->string('frequency', 20)->default('daily'); // once / daily / weekly / arrival / departure
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedTinyInteger('folio_window')->default(1);
            $table->date('last_posted_date')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('pending'); // pending / paid / cancelled
            $table->unsignedBigInteger('paid_posting_id')->nullable()->index(); // FK: kasa aşaması
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->morphs('traceable');                       // rezervasyon / profil / blok / etkinlik
            $table->string('department', 20);                  // front_office / housekeeping / concierge / fnb / accounting / spa / maintenance
            $table->date('trace_date');
            $table->time('trace_time')->nullable();
            $table->string('message', 1000);
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'department', 'trace_date', 'is_resolved'], 'pms_traces_department_lookup');
        });

        Schema::create('pms_alerts', function (Blueprint $table) {
            $table->id();
            $table->morphs('alertable');
            $table->string('area', 20)->default('check_in'); // check_in / check_out / reservation / any
            $table->string('message', 1000);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_reservation_night_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_night_id')->constrained('pms_reservation_nights')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('pms_promotions');
            $table->decimal('amount', 14, 2);                  // F: LOCAL_AMOUNT
            $table->decimal('foreign_amount', 14, 2)->nullable(); // F: FOREIGN_AMOUNT
        });

        Schema::create('pms_reservation_rebates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('rebate_code_id')->constrained('pms_rebate_codes');
            $table->string('apply_from', 20)->default('now'); // now / future_only
            $table->unsignedSmallInteger('used_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_precheckins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->char('token', 64)->unique();
            $table->string('status', 20)->default('sent'); // sent / opened / submitted / verified / rejected
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('form_data')->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->boolean('privacy_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('pms_rooms', function (Blueprint $table) {
            $table->dropForeign(['current_reservation_id']);
        });
        Schema::table('pms_blocks', function (Blueprint $table) {
            $table->dropForeign(['master_reservation_id']);
        });
        Schema::table('pms_reservations', function (Blueprint $table) {
            $table->dropForeign(['payment_token_id']);
        });
        foreach ([
            'pms_precheckins', 'pms_reservation_rebates', 'pms_reservation_night_promotions', 'pms_alerts', 'pms_traces',
            'pms_deposit_requests', 'pms_fixed_charges', 'pms_reservation_room_moves', 'pms_reservation_status_logs',
            'pms_reservation_packages', 'pms_reservation_guests', 'pms_payment_tokens', 'pms_reservation_nights',
            'pms_reservations', 'pms_bookings', 'pms_block_inventory', 'pms_blocks', 'pms_billing_instruction_codes',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
