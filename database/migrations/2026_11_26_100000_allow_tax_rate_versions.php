<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vergi oranları tarihli sürümlerle tutulur: oran değişince eski sürüm valid_to ile kapanır, yeni sürüm valid_from'dan başlar.
 * Aynı kod birden fazla sürüme sahip olabilsin diye benzersizlik (tesis, kod) yerine (tesis, kod, geçerlilik başlangıcı) olur.
 * İşlem kodlarına Fidelio kategori kısaltması (ROO, DIS, REB …) eklenir.
 * Fidelio "Tax Details" ekranındaki sıra, ayrı yazdırma, sıfır kaydetme ve Fidelio adı/departmanı da tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_taxes', function (Blueprint $table) {
            // Yeni benzersizlik önce eklenir: MySQL'de property_id yabancı anahtarı eski indeksi kullandığı için önce o kaldırılamaz.
            $table->unique(['property_id', 'code', 'valid_from']);
        });
        Schema::table('pms_taxes', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'code']);
            $table->string('source', 20)->default('manual')->after('logo_tax_code'); // manual / fidelio
            $table->unsignedSmallInteger('sort')->nullable()->after('source');          // F: Tax Details "Order"
            $table->boolean('print_separately')->default(false)->after('sort');         // F: "Print Separate"
            $table->boolean('post_zero')->default(false)->after('print_separately');    // F: "Post Zero" — sıfır tutarlı vergi satırı da yazılır
            $table->string('legacy_name', 60)->nullable()->after('post_zero');          // F: Fidelio'daki ad (ör. "VAT 18 %")
            $table->string('legacy_department', 60)->nullable()->after('legacy_name');  // F: vergi departmanı (ör. "VAT 5 %")
            $table->unsignedSmallInteger('legacy_department_number')->nullable()->after('legacy_department'); // F: vergi departman no (0–5)
        });
        Schema::table('pms_transaction_codes', function (Blueprint $table) {
            $table->string('category', 5)->nullable()->after('code');                   // F: Department Codes "Code" (ROO, DIS, REB, PEN, IND …)
        });
    }

    public function down(): void
    {
        Schema::table('pms_transaction_codes', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('pms_taxes', function (Blueprint $table) {
            $table->unique(['property_id', 'code']);
        });
        Schema::table('pms_taxes', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'code', 'valid_from']);
            $table->dropColumn(['source', 'sort', 'print_separately', 'post_zero', 'legacy_name', 'legacy_department', 'legacy_department_number']);
        });
    }
};
