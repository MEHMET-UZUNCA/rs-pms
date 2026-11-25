<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H. Cari hesaplar (City Ledger) + N3 (hesap tipi, ihtar), N2 acente komisyonları, P1 stopaj, L. Logo Tiger aktarımı.
 * Tasarım: docs/03-veritabani.md bölüm H, L, N2, N3, P1; Logo: docs/05-logo.md.
 *
 * Veritabanı kuralları (tetikleyici):
 *  - pms_ar_payments: silinemez (düzeltme ters kayıtla).
 *  - pms_logo_export_lines: Logo'ya giden fiş satırı silinemez, değiştirilemez (düzeltme yeni partiyle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_ar_account_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->decimal('credit_limit_default', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_ar_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('ar_account_type_id')->nullable()->constrained('pms_ar_account_types');
            $table->string('account_number', 30);                // F: CARDROLE 1 (A/R No)
            $table->foreignId('profile_id')->constrained('pms_profiles');
            $table->string('name', 200);
            $table->string('status', 20)->default('active');     // active / on_hold / closed
            $table->string('restricted_reason', 500)->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->char('currency_code', 3);
            $table->string('statement_email', 190)->nullable();
            $table->string('logo_cari_code', 30)->nullable()->index(); // Logo Tiger cari kodu
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'account_number']);
        });

        Schema::create('pms_ar_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('ar_account_id')->constrained('pms_ar_accounts');
            $table->foreignId('invoice_id')->nullable()->constrained('pms_invoices');
            $table->foreignId('folio_id')->nullable()->constrained('pms_folios');
            $table->date('business_date');
            $table->date('document_date');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('open_amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('open');       // open / partially_paid / paid / disputed / written_off
            $table->string('reference', 100)->nullable();
            $table->string('dispute_note', 500)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->index(['ar_account_id', 'status', 'due_date']);
        });

        Schema::create('pms_ar_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('ar_account_id')->constrained('pms_ar_accounts');
            $table->date('business_date');
            $table->date('payment_date');
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // ödeme kodu
            $table->decimal('amount', 14, 2);
            $table->decimal('unallocated_amount', 14, 2);
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('pms_cashier_shifts');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->foreignId('reversal_of_id')->nullable()->constrained('pms_ar_payments');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->index(['ar_account_id', 'business_date']);
        });

        Schema::create('pms_ar_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_payment_id')->constrained('pms_ar_payments');
            $table->foreignId('ar_invoice_id')->constrained('pms_ar_invoices');
            $table->decimal('amount', 14, 2);
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('pms_ar_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_account_id')->constrained('pms_ar_accounts');
            $table->date('statement_date');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('closing_balance', 14, 2);
            $table->string('file_path', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_ar_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_account_id')->constrained('pms_ar_accounts');
            $table->unsignedTinyInteger('level')->default(1);
            $table->timestamp('sent_at')->useCurrent();
            $table->foreignId('document_id')->nullable()->constrained('pms_documents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('pms_tax_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('ar_account_id')->constrained('pms_ar_accounts');
            $table->foreignId('ar_payment_id')->nullable()->constrained('pms_ar_payments');
            $table->foreignId('invoice_id')->nullable()->constrained('pms_invoices');
            $table->foreignId('tax_id')->nullable()->constrained('pms_taxes');
            $table->string('label', 100);
            $table->decimal('rate', 8, 4);
            $table->decimal('amount', 14, 2);
            $table->date('withheld_on');
            $table->string('certificate_number', 60)->nullable();
            $table->string('status', 20)->default('pending');    // pending / received
            $table->timestamps();
        });

        // N2. Acente komisyonları
        Schema::create('pms_commission_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_code_id')->constrained('pms_commission_codes')->cascadeOnDelete();
            $table->string('basis', 20)->default('room_net');    // room_net / room_gross / total_net
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount_per_night', 14, 2)->nullable();
            $table->foreignId('rate_code_id')->nullable()->constrained('pms_rate_codes');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
        });

        Schema::create('pms_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations');
            $table->foreignId('agent_profile_id')->constrained('pms_profiles');
            $table->foreignId('commission_code_id')->constrained('pms_commission_codes');
            $table->decimal('base_amount', 14, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('calculated'); // calculated / approved / paid / cancelled
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('ar_payment_id')->nullable()->constrained('pms_ar_payments');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['reservation_id', 'agent_profile_id']);
        });

        // L. Logo Tiger
        Schema::create('pms_logo_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('mappable_type', 30);                 // transaction_code / tax / ar_account / outlet / ledger
            $table->unsignedBigInteger('mappable_id')->nullable(); // ledger için boş, anahtar logo_key'de
            $table->string('logo_key', 30)->nullable();           // ledger adı gibi kimliksiz eşlemeler (guest / deposit / ar)
            $table->string('logo_account_code', 30);
            $table->string('logo_cost_center', 30)->nullable();
            $table->string('logo_department', 30)->nullable();
            $table->string('logo_project_code', 30)->nullable();
            $table->date('valid_from')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'mappable_type', 'mappable_id'], 'pms_logo_account_mappings_lookup');
        });

        Schema::create('pms_logo_export_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('type', 20);                           // daily_revenue / collections / ar_invoices / ar_payments / outlet_sales
            $table->string('status', 20)->default('pending');     // pending / sent / accepted / failed / cancelled
            $table->string('logo_document_number', 30)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('replaces_batch_id')->nullable()->constrained('pms_logo_export_batches');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'business_date', 'type']);
        });

        Schema::create('pms_logo_export_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pms_logo_export_batches');
            $table->unsignedInteger('line_no');
            $table->string('account_code', 30);
            $table->string('cost_center', 30)->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->char('currency_code', 3);
            $table->decimal('foreign_amount', 14, 2)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('description', 255);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unique(['batch_id', 'line_no']);
        });

        // Önceki aşamalarda açılan cari ve komisyon bağlantılarına kısıt
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->foreign('ar_account_id')->references('id')->on('pms_ar_accounts');
            $table->foreign('commission_code_id')->references('id')->on('pms_commission_codes');
        });
        Schema::table('pms_folios', function (Blueprint $table) {
            $table->foreign('ar_account_id')->references('id')->on('pms_ar_accounts');
        });
        Schema::table('pms_billing_authorizations', function (Blueprint $table) {
            $table->foreign('ar_account_id')->references('id')->on('pms_ar_accounts');
        });

        $this->createTriggers();
    }

    public function down(): void
    {
        foreach (['pms_ar_payments_no_delete', 'pms_logo_export_lines_no_delete', 'pms_logo_export_lines_no_update'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        Schema::table('pms_billing_authorizations', function (Blueprint $table) {
            $table->dropForeign(['ar_account_id']);
        });
        Schema::table('pms_folios', function (Blueprint $table) {
            $table->dropForeign(['ar_account_id']);
        });
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->dropForeign(['ar_account_id']);
            $table->dropForeign(['commission_code_id']);
        });
        foreach ([
            'pms_logo_export_lines', 'pms_logo_export_batches', 'pms_logo_account_mappings', 'pms_commissions', 'pms_commission_rules',
            'pms_commission_codes', 'pms_tax_withholdings', 'pms_ar_reminders', 'pms_ar_statements', 'pms_ar_allocations',
            'pms_ar_payments', 'pms_ar_invoices', 'pms_ar_accounts', 'pms_ar_account_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createTriggers(): void
    {
        $driver = DB::getDriverName();
        $message = 'Mali kayit degistirilemez veya silinemez; ters kayit veya yeni parti kullanin.';
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER pms_ar_payments_no_delete BEFORE DELETE ON pms_ar_payments BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            DB::unprepared("CREATE TRIGGER pms_logo_export_lines_no_delete BEFORE DELETE ON pms_logo_export_lines BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            DB::unprepared("CREATE TRIGGER pms_logo_export_lines_no_update BEFORE UPDATE ON pms_logo_export_lines BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $signal = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'";
            DB::unprepared("CREATE TRIGGER pms_ar_payments_no_delete BEFORE DELETE ON pms_ar_payments FOR EACH ROW {$signal}");
            DB::unprepared("CREATE TRIGGER pms_logo_export_lines_no_delete BEFORE DELETE ON pms_logo_export_lines FOR EACH ROW {$signal}");
            DB::unprepared("CREATE TRIGGER pms_logo_export_lines_no_update BEFORE UPDATE ON pms_logo_export_lines FOR EACH ROW {$signal}");
        }
    }
};
