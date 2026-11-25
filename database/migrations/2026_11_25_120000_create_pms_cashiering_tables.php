<?php

use App\Support\Database\FinancialRecordGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G. Kasa — folyo, yönlendirme, folyo kaydı (posting), vergi kırılımı, fatura, kasiyer/terminal/vardiya,
 * N1 (pencere tipi, rezervasyonsuz hesap grubu, vergi muafiyeti, provizyon/onay, ürünler, belge şablonları ve geçmişi),
 * N4 (hediye çekleri), P1 (onay istekleri, para aktarımı, döviz bozdurma, teminat depozitosu, ödeme bağlantısı).
 * Tasarım: docs/03-veritabani.md bölüm G, N1, N4, P1. Fidelio: PRO_ISV_POSTINGS.
 *
 * Mali kayıt kuralı veritabanı seviyesinde de zorlanır (tetikleyici):
 *  - pms_postings: silinemez; yalnız reversed_by_id ve invoice_id güncellenebilir.
 *  - pms_posting_taxes, pms_invoice_lines: silinemez, değiştirilemez.
 *  - pms_invoices: silinemez (iptal durum alanıyla yapılır).
 */
return new class extends Migration
{
    public function up(): void
    {
        $lookup = function (string $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('pms_properties');
                $table->string('code', 10);
                $table->string('name', 100);
                $table->timestamps();
                $table->unique(['property_id', 'code']);
            });
        };
        $lookup('pms_window_types');   // F: Billing Window Types
        $lookup('pms_folio_groups');   // F: Financial Account Groups
        $lookup('pms_article_groups'); // F: Article Groups

        Schema::create('pms_cashier_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedBigInteger('outlet_id')->nullable()->index(); // FK: Simphony aşaması
            $table->decimal('default_float', 14, 2)->default(0);
            $table->string('printer_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->unsignedSmallInteger('number');            // F: CASHIER_NUMBER
            $table->string('initials', 3)->nullable();         // F: CASHIER_INITIALS
            $table->string('name', 100);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_interface')->default(false);   // Simphony gibi arayüz kasiyeri
            $table->string('pin_hash', 255)->nullable();
            $table->unsignedTinyInteger('pin_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->decimal('max_cash_refund', 14, 2)->nullable();
            $table->json('allowed_payment_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();       // F: CASHIER_ID
            $table->timestamps();
            $table->unique(['property_id', 'number']);
        });

        Schema::create('pms_cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('cashier_id')->constrained('pms_cashiers');
            $table->foreignId('cashier_terminal_id')->nullable()->constrained('pms_cashier_terminals');
            $table->date('business_date');
            $table->string('status', 10)->default('open');     // open / closed
            $table->json('opening_float')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['property_id', 'business_date', 'status']);
        });

        Schema::create('pms_folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('folio_number', 20);
            $table->string('type', 20)->default('guest'); // guest / master / passer_by / pseudo_room / house_account / ar / event
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->unsignedTinyInteger('window')->default(1);   // 1..8 — F: WINDOW_NUMBER
            $table->string('name', 100)->nullable();             // F: FA_LONGDESC
            $table->foreignId('folio_group_id')->nullable()->constrained('pms_folio_groups');
            $table->foreignId('window_type_id')->nullable()->constrained('pms_window_types');
            $table->date('valid_from')->nullable();              // F: FA_EXPVALIDFROM
            $table->date('valid_until')->nullable();             // F: FA_EXPVALIDUNTIL
            $table->date('actual_from')->nullable();             // F: FA_ACTVALIDFROM
            $table->date('actual_until')->nullable();            // F: FA_ACTVALIDUNTIL
            $table->foreignId('payee_profile_id')->nullable()->constrained('pms_profiles'); // F: WINDOW_INVOICE_TO_PROFILE_ID
            $table->unsignedBigInteger('ar_account_id')->nullable()->index(); // FK: cari aşaması
            $table->unsignedBigInteger('event_booking_id')->nullable()->index(); // FK: banket aşaması
            $table->string('status', 10)->default('open');       // open / closed
            $table->decimal('balance', 14, 2)->default(0);
            $table->date('opened_business_date');
            $table->date('closed_business_date')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->string('legacy_id', 50)->nullable();         // F: WINDOW_ID / FA_ID
            $table->timestamps();
            $table->unique(['property_id', 'folio_number']);
            $table->unique(['reservation_id', 'window']);
            $table->index(['property_id', 'type', 'status']);
        });

        Schema::create('pms_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('source_reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('target_type', 20);                   // window / reservation / folio
            $table->unsignedTinyInteger('target_window')->nullable();
            $table->foreignId('target_reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('target_folio_id')->nullable()->constrained('pms_folios');
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->foreignId('transaction_code_group_id')->nullable()->constrained('pms_transaction_code_groups');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('invoice_number', 30);
            $table->string('series', 10)->nullable();
            $table->string('type', 20)->default('invoice');      // invoice / proforma / credit_note
            $table->string('status', 20)->default('issued');     // issued / cancelled
            $table->foreignId('folio_id')->constrained('pms_folios');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('bill_to_profile_id')->nullable()->constrained('pms_profiles');
            $table->string('bill_to_name', 250);
            $table->string('bill_to_tax_office', 100)->nullable();
            $table->string('bill_to_tax_number', 30)->nullable();
            $table->string('bill_to_address', 500)->nullable();
            $table->date('business_date');
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency_code', 3);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->foreignId('credit_note_for_id')->nullable()->constrained('pms_invoices');
            $table->foreignId('replaces_invoice_id')->nullable()->constrained('pms_invoices');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancel_reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->string('pdf_path', 500)->nullable();
            $table->string('e_document_uuid', 64)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'invoice_number']);
            $table->index(['property_id', 'business_date']);
        });

        Schema::create('pms_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');                       // F: POSTING_DATE
            $table->foreignId('folio_id')->constrained('pms_folios');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('room_id')->nullable()->constrained('pms_rooms');
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // F: POSTING_DC_ID
            $table->string('ledger', 10)->default('guest');      // guest / deposit / ar / package
            $table->string('kind', 20);                          // charge / payment / adjustment / transfer / deposit / deposit_transfer / paid_out / exchange / exchange_difference / allowance / discount
            $table->string('description', 255);
            $table->string('reference', 100)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);    // net
            $table->decimal('gross_unit_price', 14, 2)->default(0);
            $table->decimal('amount', 14, 2);                    // işaretli, vergi dahil, tesis para birimi
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->char('foreign_currency', 3)->nullable();
            $table->decimal('foreign_unit_price', 14, 2)->nullable();
            $table->decimal('foreign_amount', 14, 2)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->date('stay_date')->nullable();
            $table->foreignId('reservation_night_id')->nullable()->constrained('pms_reservation_nights');
            $table->foreignId('package_id')->nullable()->constrained('pms_packages');
            $table->unsignedBigInteger('outlet_id')->nullable();          // FK: Simphony aşaması
            $table->unsignedBigInteger('event_id')->nullable()->index();  // FK: banket aşaması
            $table->unsignedBigInteger('pos_posting_id')->nullable()->index(); // FK: Simphony aşaması
            $table->string('pos_check_number', 30)->nullable();
            $table->foreignId('market_segment_id')->nullable()->constrained('pms_market_segments');
            $table->foreignId('origin_reservation_id')->nullable()->constrained('pms_reservations'); // F: POSTING_ORG_RES_ID
            $table->foreignId('origin_folio_id')->nullable()->constrained('pms_folios');             // F: POSTING_ORG_FA_ID
            $table->foreignId('origin_profile_id')->nullable()->constrained('pms_profiles');         // F: POSTING_ORG_PROFILE_ID
            $table->foreignId('discount_of_id')->nullable()->constrained('pms_postings');           // F: REBATING_POSTING_ID
            $table->foreignId('rebate_code_id')->nullable()->constrained('pms_rebate_codes');       // F: ZRDF
            $table->string('source', 20)->default('manual');     // manual / night_audit / fixed_charge / simphony / rsone / transfer / import
            $table->foreignId('cashier_shift_id')->nullable()->constrained('pms_cashier_shifts');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->foreignId('reversal_of_id')->nullable()->constrained('pms_postings');
            $table->foreignId('reversed_by_id')->nullable()->constrained('pms_postings');
            $table->foreignId('transfer_from_id')->nullable()->constrained('pms_postings');
            $table->foreignId('reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->string('note', 500)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('pms_invoices');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->string('legacy_id', 50)->nullable();          // F: POSTING_ID
            $table->unsignedTinyInteger('legacy_ledger_status')->nullable(); // F: POSTING_LEDGERSTATUS
            $table->timestamp('created_at')->useCurrent();       // F: POSTING_PCDATE

            $table->foreign('foreign_currency')->references('code')->on('pms_currencies');
            $table->index(['property_id', 'business_date']);
            $table->index(['transaction_code_id', 'business_date']);
            $table->index(['outlet_id', 'business_date']);
            $table->index('legacy_id');
        });

        Schema::create('pms_posting_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_id')->constrained('pms_postings');
            $table->foreignId('tax_id')->constrained('pms_taxes');
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('base_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2);
        });

        Schema::create('pms_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('pms_invoices');
            $table->foreignId('posting_id')->nullable()->constrained('pms_postings');
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->string('description', 255);
            $table->date('stay_date')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('gross_amount', 14, 2);
            $table->json('tax_breakdown')->nullable();
        });

        Schema::create('pms_cashier_shift_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('pms_cashier_shifts')->cascadeOnDelete();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes'); // ödeme kodu
            $table->char('currency_code', 3);
            $table->decimal('opening_amount', 14, 2)->default(0);
            $table->decimal('system_amount', 14, 2)->default(0);
            $table->decimal('counted_amount', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['shift_id', 'transaction_code_id', 'currency_code'], 'pms_cashier_shift_totals_unique');
        });

        Schema::create('pms_tax_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->foreignId('tax_id')->constrained('pms_taxes');
            $table->morphs('exemptable');                         // profil / rezervasyon
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('document_no', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_billing_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('type', 20);                           // card_preauth / city_ledger / advance_billing
            $table->foreignId('payment_token_id')->nullable()->constrained('pms_payment_tokens');
            $table->unsignedBigInteger('ar_account_id')->nullable()->index(); // FK: cari aşaması
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('approval_code', 60)->nullable();
            $table->string('status', 20)->default('requested');   // requested / approved / declined / released
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('type', 20);                           // allowance / adjustment / rate_override / refund / write_off / paid_out / ar_allocation
            $table->morphs('approvable');
            $table->decimal('amount', 14, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('pms_reason_codes');
            $table->text('reason');
            $table->string('status', 20)->default('pending');     // pending / approved / rejected / cancelled
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });

        Schema::create('pms_cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->string('kind', 20);                           // drop / transfer / float / bank_deposit
            $table->foreignId('from_shift_id')->nullable()->constrained('pms_cashier_shifts');
            $table->foreignId('to_shift_id')->nullable()->constrained('pms_cashier_shifts');
            $table->char('currency_code', 3);
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('requested');   // requested / received / cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->date('business_date');
            $table->foreignId('cashier_shift_id')->constrained('pms_cashier_shifts');
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->foreignId('folio_id')->nullable()->constrained('pms_folios');
            $table->string('direction', 4);                       // buy / sell
            $table->char('currency_code', 3);
            $table->decimal('foreign_amount', 14, 2);
            $table->decimal('exchange_rate', 18, 8);
            $table->decimal('local_amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->string('document_no', 30)->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_security_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations');
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->string('method', 20);                         // cash / card_preauth / transfer
            foreach (['required_amount', 'collected_amount', 'withheld_amount', 'refunded_amount'] as $amount) {
                $table->decimal($amount, 14, 2)->default(0);
            }
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('required');    // required / collected / partially_refunded / refunded / withheld
            $table->string('reason', 500)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('folio_id')->nullable()->constrained('pms_folios');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->string('provider', 30);
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->string('url', 500);
            $table->string('external_id', 100)->nullable();
            $table->string('status', 20)->default('created');     // created / paid / expired / cancelled
            $table->foreignId('paid_posting_id')->nullable()->constrained('pms_postings');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
        });

        Schema::create('pms_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('article_group_id')->nullable()->constrained('pms_article_groups');
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->decimal('price', 14, 2);
            $table->string('barcode', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('type', 30);                           // invoice / proforma / information_folio / confirmation / registration_card / statement / reminder / voucher
            $table->char('language_code', 2)->default('tr');
            $table->string('name', 100);
            $table->longText('body');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['property_id', 'type', 'language_code']);
        });

        Schema::create('pms_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->morphs('documentable');
            $table->foreignId('template_id')->nullable()->constrained('pms_document_templates');
            $table->string('type', 30);
            $table->string('number', 30)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('sent_to', 190)->nullable();
            $table->string('channel', 10)->default('print');      // print / email / sms
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pms_voucher_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('barcode_prefix', 20)->nullable();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes');
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('voucher_type_id')->constrained('pms_voucher_types');
            $table->string('number', 40);
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            $table->foreignId('issued_to_profile_id')->nullable()->constrained('pms_profiles');
            $table->timestamp('issued_at')->useCurrent();
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('active');      // active / used / expired / cancelled
            $table->foreignId('used_posting_id')->nullable()->constrained('pms_postings');
            $table->unsignedBigInteger('rsone_gift_voucher_id')->nullable()->index();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'number']);
        });

        // Önceki aşamalarda açılan kayıt bağlantılarına kısıt
        Schema::table('pms_reservation_nights', function (Blueprint $table) {
            $table->foreign('posted_posting_id')->references('id')->on('pms_postings');
        });
        Schema::table('pms_deposit_requests', function (Blueprint $table) {
            $table->foreign('paid_posting_id')->references('id')->on('pms_postings');
        });
        Schema::table('pms_reservation_transports', function (Blueprint $table) {
            $table->foreign('posting_id')->references('id')->on('pms_postings');
        });

        FinancialRecordGuard::protectCashiering();
    }

    public function down(): void
    {
        foreach (['pms_postings', 'pms_posting_taxes', 'pms_invoice_lines', 'pms_invoices'] as $guarded) {
            FinancialRecordGuard::drop($guarded);
        }
        Schema::table('pms_reservation_transports', function (Blueprint $table) {
            $table->dropForeign(['posting_id']);
        });
        Schema::table('pms_deposit_requests', function (Blueprint $table) {
            $table->dropForeign(['paid_posting_id']);
        });
        Schema::table('pms_reservation_nights', function (Blueprint $table) {
            $table->dropForeign(['posted_posting_id']);
        });
        foreach ([
            'pms_vouchers', 'pms_voucher_types', 'pms_documents', 'pms_document_templates', 'pms_articles', 'pms_payment_links',
            'pms_security_deposits', 'pms_currency_exchanges', 'pms_cash_transfers', 'pms_approval_requests',
            'pms_billing_authorizations', 'pms_tax_exemptions', 'pms_cashier_shift_totals', 'pms_invoice_lines',
            'pms_posting_taxes', 'pms_postings', 'pms_invoices', 'pms_routing_rules', 'pms_folios', 'pms_cashier_shifts',
            'pms_cashiers', 'pms_cashier_terminals', 'pms_article_groups', 'pms_folio_groups', 'pms_window_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
