<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Döviz kurları (Fidelio Cashiering → Exchange Rates): banka kurları (KKTC Merkez Bankası döviz/efektif alış-satış) ve otel kurları
 * (Rate, Cash Rate, Ratecode Rate). Otel kuru = dayanak banka kuru × (1 + kâr payı %); kural para birimi başına.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_exchange_rates', function (Blueprint $table) {
            $table->decimal('cash_buy_rate', 18, 8)->nullable()->after('sell_rate');    // banka efektif alış
            $table->decimal('cash_sell_rate', 18, 8)->nullable()->after('cash_buy_rate'); // banka efektif satış
            $table->decimal('cash_rate', 18, 8)->nullable()->after('posting_rate');      // F: Cash Rate
            $table->decimal('ratecode_rate', 18, 8)->nullable()->after('cash_rate');     // F: Ratecode Rate
        });
        (new ProisvViews)->create();

        Schema::create('pms_exchange_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->char('currency_code', 3);
            $table->char('source_currency_code', 3)->nullable();   // bankadaki karşılığı (ör. EUP "Pay Euro" → EUR); boşsa aynısı
            $table->string('base', 12)->default('forex_buy');      // forex_buy / forex_sell / cash_buy / cash_sell
            $table->decimal('rate_margin_pct', 6, 2)->default(0);       // Rate: + kâr / − indirim
            $table->decimal('cash_margin_pct', 6, 2)->default(0);       // Cash Rate
            $table->decimal('ratecode_margin_pct', 6, 2)->default(0);   // Ratecode Rate
            $table->boolean('is_auto')->default(true);                 // günlük servis bu kuru güncellesin mi
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_exchange_rate_rules');
        (new ProisvViews)->drop();
        Schema::table('pms_exchange_rates', fn (Blueprint $table) => $table->dropColumn(['cash_buy_rate', 'cash_sell_rate', 'cash_rate', 'ratecode_rate']));
        (new ProisvViews)->create();
    }
};
