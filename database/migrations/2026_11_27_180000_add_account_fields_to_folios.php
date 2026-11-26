<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "New Financial Account" (PM hesabı) alanları: pazar, kaynak, sözleşme para birimi, kredi limiti, fatura bilgisi,
 * "No Post" (harcama yazılamaz, yalnız ödeme), hesabı açan kullanıcı.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_folios', function (Blueprint $table) {
            $table->foreignId('market_segment_id')->nullable()->after('payee_profile_id')->constrained('pms_market_segments');
            $table->foreignId('source_code_id')->nullable()->after('market_segment_id')->constrained('pms_source_codes');
            $table->char('currency_code', 3)->nullable()->after('source_code_id');
            $table->decimal('credit_limit', 14, 2)->nullable()->after('currency_code');
            $table->string('billing_info', 500)->nullable()->after('credit_limit');   // F: Billing Info
            $table->boolean('no_post')->default(false)->after('billing_info');       // F: No Post
            $table->foreignId('opened_by')->nullable()->after('no_post')->constrained('users')->nullOnDelete();
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_folios', function (Blueprint $table) {
            foreach (['market_segment_id', 'source_code_id', 'opened_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['currency_code', 'credit_limit', 'billing_info', 'no_post']);
        });
        (new ProisvViews)->create();
    }
};
