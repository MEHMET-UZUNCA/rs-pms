<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A. Çekirdek — tesis, yetki, ayar, iş günü, döviz, vergi, numara serisi, gerekçe, ülke, denetim.
 * Tasarım: docs/03-veritabani.md bölüm A.
 *
 * İleriki aşamalarda oluşacak tablolara giden alanlar burada indeksli tam sayı olarak açılır;
 * yabancı anahtar kısıtı o tablo oluşturulurken eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();                 // ISO3
            $table->string('short_code', 3)->nullable();        // F: ZCUR_SHORTDESC
            $table->string('name', 60);
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2); // F: ZCUR_DECDIGITS
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);  // F: ZCUR_DISPLORDER
            $table->string('rate_period', 10)->default('daily'); // daily / monthly — F: ZCUR_MONTHLY
            $table->string('legacy_id', 50)->nullable();        // F: ZCUR_ID
            $table->timestamps();
        });

        Schema::create('pms_countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->char('iso3', 3)->nullable();
            $table->string('fidelio_code', 3)->nullable()->index(); // F: COUNTRY / NATION
            $table->string('name_tr', 100);
            $table->string('name_en', 100);
            $table->string('nationality_tr', 100)->nullable();
            $table->string('kbs_code', 10)->nullable();             // F: UYRUK_KODU
            $table->string('phone_prefix', 10)->nullable();
            $table->string('legacy_id', 50)->nullable();            // F: XCOU_ID
            $table->timestamps();
        });

        Schema::create('pms_properties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 190)->nullable();
            $table->string('timezone', 50)->default('Asia/Nicosia');
            $table->char('base_currency', 3);
            $table->time('check_in_time')->default('14:00:00');
            $table->time('check_out_time')->default('12:00:00');
            $table->date('current_business_date');
            $table->time('night_audit_time')->nullable();
            $table->unsignedBigInteger('exchange_diff_transaction_code_id')->nullable()->index(); // F: 550 Kur Farki — FK: kasa aşaması
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->unsignedBigInteger('rsone_business_id')->nullable()->index(); // RS ONE businesses.id
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('base_currency')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('pms_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->string('group', 40)->index();
            $table->timestamps();
        });

        Schema::create('pms_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('pms_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('pms_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('pms_user_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pms_properties')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('pms_roles');
            $table->decimal('max_discount_pct', 5, 2)->nullable();
            $table->decimal('max_reversal_amount', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'property_id', 'role_id']);
        });

        Schema::create('pms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('pms_properties')->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'key']);
        });

        Schema::create('pms_business_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('status', 20)->default('open'); // open / closing / closed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('night_audit_run_id')->nullable()->index(); // FK: gece kapanışı aşaması
            $table->timestamps();
            $table->unique(['property_id', 'business_date']);
        });

        Schema::create('pms_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->char('currency_code', 3);
            $table->date('business_date');                       // F: WDAT_DATE
            $table->string('rate_type', 10)->default('day');     // day / month / year
            $table->decimal('buy_rate', 18, 8)->nullable();
            $table->decimal('sell_rate', 18, 8)->nullable();
            $table->decimal('posting_rate', 18, 8);              // Folyoda kullanılan kur
            $table->decimal('exchange_fee_pct', 5, 2)->nullable();
            $table->string('source', 20)->default('manual');     // manual / central_bank / fidelio
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'currency_code', 'business_date', 'rate_type'], 'pms_exchange_rates_unique');
        });

        Schema::create('pms_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('calc_type', 20)->default('percent'); // percent / per_night / per_person_night
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->boolean('is_inclusive')->default(true);
            $table->string('logo_tax_code', 20)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('type', 30); // invoice / credit_note / proforma / receipt / confirmation / folio / ar_invoice / cancellation ...
            $table->string('prefix', 10)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->boolean('reset_yearly')->default(false);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'type']);
        });

        Schema::create('pms_reason_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('category', 30); // cancel / no_show / room_move / rate_override / posting_reversal / discount / outage / waive / complimentary / early_departure / reinstate / waitlist / turnaway / lost_business
            $table->string('code', 10);
            $table->string('name', 100);
            $table->boolean('requires_note')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'category', 'code']);
        });

        Schema::create('pms_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('pms_properties')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 30); // created / updated / deleted / status_changed / reversed / printed / exported
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('pms_reason_codes')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->date('business_date')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['property_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_audit_logs', 'pms_reason_codes', 'pms_number_sequences', 'pms_taxes', 'pms_exchange_rates',
            'pms_business_dates', 'pms_settings', 'pms_user_properties', 'pms_role_permissions',
            'pms_permissions', 'pms_roles', 'pms_properties', 'pms_countries', 'pms_currencies',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
