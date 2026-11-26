<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "Prices" sekmesi: kişi sayısı Default / 1–4 için fiyat ve hafta sonu fiyatı; çocuk yaş kategorisi (CA1, CA2 …) için fiyat ve
 * hafta sonu fiyatı. Hafta sonu günleri fiyat kodunda (weekend_days). Boş (null) fiyat = tanımsız; 0 = ücretsiz.
 * Fiyat koduna "Complimentary" türü.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_rate_amounts', function (Blueprint $table) {
            $table->decimal('price_default', 14, 2)->nullable()->after('weekdays');
            foreach (['weekend_price_default', 'weekend_price_1', 'weekend_price_2', 'weekend_price_3', 'weekend_price_4',
                'weekend_child_band_1', 'weekend_child_band_2', 'weekend_child_band_3'] as $column) {
                $table->decimal($column, 14, 2)->nullable()->after('extra_bed');
            }
        });
        Schema::table('pms_rate_codes', function (Blueprint $table) {
            $table->string('complimentary_type', 12)->nullable()->after('is_negotiated');   // F: Complimentary — comp / house_use
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_rate_amounts', fn (Blueprint $table) => $table->dropColumn(['price_default', 'weekend_price_default', 'weekend_price_1', 'weekend_price_2',
            'weekend_price_3', 'weekend_price_4', 'weekend_child_band_1', 'weekend_child_band_2', 'weekend_child_band_3']));
        Schema::table('pms_rate_codes', fn (Blueprint $table) => $table->dropColumn('complimentary_type'));
        (new ProisvViews)->create();
    }
};
