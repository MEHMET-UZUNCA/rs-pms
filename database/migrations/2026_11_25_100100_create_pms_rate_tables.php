<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E. Fiyat ve müsaitlik (+ N6 gün tipleri, kaçan talep, overbooking; P3 fiyat sınırları; promosyon ve indirim kuralları).
 * Tasarım: docs/03-veritabani.md bölüm E, N6, P3.
 * Rezervasyona bağlı tablolar (gece promosyonu, rezervasyon indirimi) 3. aşamada oluşturulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $code = function (Blueprint $table, int $length = 10) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', $length);
            $table->string('name', 100);
        };

        Schema::create('pms_seasons', function (Blueprint $table) use ($code) {
            $code($table);
            $table->char('color', 7)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_season_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('pms_seasons')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->index(['date_from', 'date_to']);
        });

        Schema::create('pms_rate_categories', function (Blueprint $table) use ($code) {
            $code($table);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_day_types', function (Blueprint $table) use ($code) {
            $code($table);                                   // F: Day Types
            $table->char('color', 7)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_day_type_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('date');
            $table->foreignId('day_type_id')->constrained('pms_day_types');
            $table->unique(['property_id', 'date']);
        });

        Schema::create('pms_child_age_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->unsignedTinyInteger('band');             // 1-3 — F: CA1..CA3
            $table->unsignedTinyInteger('age_from');
            $table->unsignedTinyInteger('age_to');
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['property_id', 'band']);
        });

        Schema::create('pms_cancellation_policies', function (Blueprint $table) use ($code) {
            $code($table);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_cancellation_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('pms_cancellation_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before_arrival');
            $table->time('hour')->nullable();
            $table->string('penalty_type', 10);              // nights / percent / amount
            $table->decimal('penalty_value', 14, 2);
            $table->boolean('applies_to_no_show')->default(false);
        });

        Schema::create('pms_deposit_policies', function (Blueprint $table) use ($code) {
            $code($table);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_deposit_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('pms_deposit_policies')->cascadeOnDelete();
            $table->string('due_type', 30);                  // on_booking / days_before_arrival
            $table->unsignedSmallInteger('due_days')->default(0);
            $table->string('amount_type', 10);               // percent / nights / amount
            $table->decimal('amount_value', 14, 2);
        });

        Schema::create('pms_packages', function (Blueprint $table) use ($code) {
            $code($table);                                   // BB / HB / FB / AI / SPA1
            $table->string('type', 10)->default('board');    // board / addon
            $table->string('posting_rhythm', 20)->default('every_night'); // every_night / arrival / departure / once
            $table->boolean('is_included_in_rate')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_package_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('pms_packages')->cascadeOnDelete();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // 110 Pension Breakfast …
            $table->string('calc', 20)->default('per_person'); // per_room / per_person / per_adult / per_child
            $table->decimal('amount_adult', 14, 2)->default(0);
            $table->decimal('amount_child', 14, 2)->default(0);
            $table->boolean('is_allowance')->default(false);
            $table->unsignedBigInteger('outlet_id')->nullable()->index(); // FK: Simphony aşaması
        });

        Schema::create('pms_rate_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('rate_category_id')->nullable()->constrained('pms_rate_categories');
            $table->string('code', 20);                      // F: RATECODE
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->char('currency_code', 3);
            $table->foreignId('package_id')->nullable()->constrained('pms_packages'); // F: RATECODE_BOARDTYPE
            $table->unsignedSmallInteger('package_nights')->nullable();
            $table->boolean('is_tax_inclusive')->default(true);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_negotiated')->default(false);
            $table->foreignId('parent_rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->string('derive_type', 10)->nullable();   // percent / amount
            $table->decimal('derive_value', 14, 2)->nullable();
            $table->string('rounding', 10)->nullable();      // none / 1 / 5 / 10
            $table->foreignId('room_transaction_code_id')->constrained('pms_transaction_codes');
            $table->foreignId('default_market_segment_id')->nullable()->constrained('pms_market_segments');
            $table->foreignId('default_source_code_id')->nullable()->constrained('pms_source_codes');
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('pms_cancellation_policies');
            $table->foreignId('deposit_policy_id')->nullable()->constrained('pms_deposit_policies');
            $table->unsignedSmallInteger('min_los')->nullable();
            $table->unsignedSmallInteger('max_los')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_rate_code_room_types', function (Blueprint $table) {
            $table->foreignId('rate_code_id')->constrained('pms_rate_codes')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('pms_room_types')->cascadeOnDelete();
            $table->primary(['rate_code_id', 'room_type_id']);
        });

        Schema::create('pms_rate_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_code_id')->constrained('pms_rate_codes')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->foreignId('day_type_id')->nullable()->constrained('pms_day_types');
            $table->date('date_from');
            $table->date('date_to');                         // dahil
            $table->unsignedTinyInteger('weekdays')->default(127); // Pzt=1 … Paz=64
            foreach (['price_1', 'price_2', 'price_3', 'price_4', 'extra_adult', 'child_band_1', 'child_band_2', 'child_band_3', 'extra_bed'] as $price) {
                $table->decimal($price, 14, 2)->nullable();
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['rate_code_id', 'room_type_id', 'date_from', 'date_to'], 'pms_rate_amounts_lookup');
        });

        Schema::create('pms_rate_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->foreignId('room_type_id')->nullable()->constrained('pms_room_types');
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->unsignedSmallInteger('min_los')->nullable();
            $table->unsignedSmallInteger('max_los')->nullable();
            $table->unsignedSmallInteger('min_advance_days')->nullable();
            $table->unsignedSmallInteger('max_advance_days')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'rate_code_id', 'room_type_id', 'date'], 'pms_rate_restrictions_unique');
        });

        Schema::create('pms_inventory_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->date('date');
            foreach (['physical_rooms', 'out_of_order', 'out_of_service', 'sold_definite', 'sold_tentative',
                'block_remaining_definite', 'block_remaining_tentative', 'overbooking_limit'] as $count) {
                $table->unsignedSmallInteger($count)->default(0);
            }
            $table->smallInteger('available')->default(0);   // eksiye düşebilir (overbooking)
            $table->timestamp('updated_at')->nullable();
            $table->unique(['property_id', 'room_type_id', 'date']);
        });

        Schema::create('pms_overbooking_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_type_id')->nullable()->constrained('pms_room_types'); // null = tüm otel
            $table->date('date');
            $table->unsignedSmallInteger('limit');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'room_type_id', 'date']);
        });

        Schema::create('pms_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 3);                       // F: PROM_SHORTDESC
            $table->string('name', 100);                     // F: PROM_LONGDESC
            $table->string('promo_code', 30)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->string('rule_code', 3)->nullable();      // F: RULE_SHORTDESC
            $table->string('rule_name', 100)->nullable();    // F: RULE_LONGDESC
            $table->string('rule_type', 20);                 // free_nights / percent / amount — F: RULE_TYPE
            $table->decimal('value', 14, 2);
            $table->date('stay_from')->nullable();
            $table->date('stay_to')->nullable();
            $table->date('booking_from')->nullable();
            $table->date('booking_to')->nullable();
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_rebate_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);                      // F: ZRDF_SHORTDESC
            $table->string('name', 100);                     // F: ZRDF_LONGDESC
            $table->string('barcode_prefix', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_rebate_code_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rebate_code_id')->constrained('pms_rebate_codes')->cascadeOnDelete();
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->foreignId('transaction_code_group_id')->nullable()->constrained('pms_transaction_code_groups');
            $table->string('type', 20);                      // percent / amount / free_count
            $table->decimal('value', 14, 2);
            $table->unsignedSmallInteger('max_count')->nullable();
            $table->foreignId('discount_transaction_code_id')->nullable()->constrained('pms_transaction_codes');
        });

        Schema::create('pms_rate_guardrails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_type_id')->nullable()->constrained('pms_room_types');
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->decimal('floor_price', 14, 2)->nullable();
            $table->decimal('ceiling_price', 14, 2)->nullable();
            $table->unsignedTinyInteger('weekdays')->default(127);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_occupancy_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_type_id')->nullable()->constrained('pms_room_types');
            $table->decimal('occupancy_from_pct', 5, 2);
            $table->decimal('premium_pct', 5, 2);
            $table->decimal('min_rate', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_turnaways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->date('arrival_date');
            $table->unsignedSmallInteger('nights');
            $table->foreignId('room_type_id')->nullable()->constrained('pms_room_types');
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->unsignedSmallInteger('persons')->default(1);
            $table->foreignId('reason_code_id')->constrained('pms_reason_codes');
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('source_code_id')->nullable()->constrained('pms_source_codes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['property_id', 'arrival_date']);
        });

        // 1. aşamada açılan alanlara kısıtlar
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->foreign('default_rate_code_id')->references('id')->on('pms_rate_codes');
        });
        Schema::table('pms_profile_negotiated_rates', function (Blueprint $table) {
            $table->foreign('rate_code_id')->references('id')->on('pms_rate_codes');
        });
    }

    public function down(): void
    {
        Schema::table('pms_profile_negotiated_rates', function (Blueprint $table) {
            $table->dropForeign(['rate_code_id']);
        });
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->dropForeign(['default_rate_code_id']);
        });
        foreach ([
            'pms_turnaways', 'pms_occupancy_rate_rules', 'pms_rate_guardrails', 'pms_rebate_code_rules', 'pms_rebate_codes',
            'pms_promotions', 'pms_overbooking_limits', 'pms_inventory_daily', 'pms_rate_restrictions', 'pms_rate_amounts',
            'pms_rate_code_room_types', 'pms_rate_codes', 'pms_package_elements', 'pms_packages', 'pms_deposit_policy_rules',
            'pms_deposit_policies', 'pms_cancellation_policy_rules', 'pms_cancellation_policies', 'pms_child_age_bands',
            'pms_day_type_calendar', 'pms_day_types', 'pms_rate_categories', 'pms_season_dates', 'pms_seasons',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
