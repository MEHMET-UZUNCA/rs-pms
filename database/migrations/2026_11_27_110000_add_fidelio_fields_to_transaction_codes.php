<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "Department Codes → Edit" penceresindeki alanlar (docs/fidelio/eslesme.md §16) ve tarihli vergi bağlantısı.
 * Fidelio'da bir kodun vergisi tarihlidir ("1.01.2001 – … VAT 5 %"): kodun vergisi bir tarihten itibaren değişebilir.
 * PROISV view'ları bu tablolara dayandığı için önce kaldırılır, sonda yeniden kurulur (SQLite tabloyu yeniden kurarken view'lar bozulur).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_transaction_codes', function (Blueprint $table) {
            $table->string('folio_text', 100)->nullable()->after('name');                       // F: Folio Text
            $table->string('arrangement_code', 10)->nullable()->after('folio_text');            // F: Arrangement (ör. ACC)
            $table->string('government_code', 20)->nullable()->after('arrangement_code');       // F: Government Code
            $table->foreignId('adjustment_transaction_code_id')->nullable()->after('discount_of_transaction_code_id')
                ->constrained('pms_transaction_codes');                                         // F: Adjustment Dept (ör. 100 → 102)
            $table->foreignId('return_transaction_code_id')->nullable()->after('adjustment_transaction_code_id')
                ->constrained('pms_transaction_codes');                                         // F: Return Dep. (boş = aynı kod)
            $table->string('unit_description', 50)->nullable()->after('default_price');         // F: Unit Description
            $table->string('physical_origin', 30)->nullable()->after('unit_description');       // F: Physical Origin
            $table->boolean('disabled_in_prearrival')->default(false)->after('manual_post_allowed');
            $table->boolean('disabled_in_billing')->default(false)->after('disabled_in_prearrival');
            $table->boolean('disabled_in_ar')->default(false)->after('disabled_in_billing');
            $table->boolean('disabled_in_payments')->default(false)->after('disabled_in_ar');
            $table->boolean('direct_debit')->default(false)->after('disabled_in_payments');      // F: Direct Debit Payment
            $table->boolean('non_hotel_revenue')->default(false)->after('direct_debit');         // F: Non-Hotel Revenue
            $table->foreignId('cc_commission_transaction_code_id')->nullable()->after('non_hotel_revenue')
                ->constrained('pms_transaction_codes');                                         // F: CC Commission
            $table->decimal('cc_commission_pct', 5, 2)->nullable()->after('cc_commission_transaction_code_id'); // F: Percentage
            $table->decimal('credit_limit', 14, 2)->nullable()->after('cc_commission_pct');      // F: Credit Limit
            $table->string('folio_subfunction', 30)->nullable()->after('credit_limit');          // F: Folio Subfunction
            $table->boolean('open_cash_drawer')->default(false)->after('folio_subfunction');     // F: Open Cash Drawer
            $table->boolean('hostess_enabled')->default(false)->after('open_cash_drawer');       // F: Hostess Enabled
            $table->boolean('post_tax_only')->default(false)->after('hostess_enabled');          // F: Post Tax Only
        });

        Schema::table('pms_transaction_code_taxes', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('calc_base');
            $table->date('valid_to')->nullable()->after('valid_from');
            // Yeni benzersizlik önce eklenir: MySQL'de transaction_code_id yabancı anahtarı eski indeksi kullanıyor.
            $table->unique(['transaction_code_id', 'tax_id', 'valid_from'], 'pms_tc_taxes_code_tax_from_unique');
        });
        Schema::table('pms_transaction_code_taxes', function (Blueprint $table) {
            $table->dropUnique(['transaction_code_id', 'tax_id']);
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_transaction_code_taxes', function (Blueprint $table) {
            $table->unique(['transaction_code_id', 'tax_id']);
        });
        Schema::table('pms_transaction_code_taxes', function (Blueprint $table) {
            $table->dropUnique('pms_tc_taxes_code_tax_from_unique');
            $table->dropColumn(['valid_from', 'valid_to']);
        });
        Schema::table('pms_transaction_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjustment_transaction_code_id');
            $table->dropConstrainedForeignId('return_transaction_code_id');
            $table->dropConstrainedForeignId('cc_commission_transaction_code_id');
            $table->dropColumn(['folio_text', 'arrangement_code', 'government_code', 'unit_description', 'physical_origin',
                'disabled_in_prearrival', 'disabled_in_billing', 'disabled_in_ar', 'disabled_in_payments', 'direct_debit', 'non_hotel_revenue',
                'cc_commission_pct', 'credit_limit', 'folio_subfunction', 'open_cash_drawer', 'hostess_enabled', 'post_tax_only']);
        });
        (new ProisvViews)->create();
    }
};
