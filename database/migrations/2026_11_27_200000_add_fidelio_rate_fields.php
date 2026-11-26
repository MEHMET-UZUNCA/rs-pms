<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "Rate Edit" alanları.
 *  - Fiyat kodu (Rate sekmesi): folyo metni, hafta sonu departman kodu, erken rezervasyon günleri (min/max), hafta sonu günleri,
 *    girişte kur yenileme, sabit / düz fiyat, fiyatı yazdır.
 *  - Fiyat detayı (Rate Details): birden çok oda tipi (pivot; boşsa bütün oda tipleri), belirli oda numaraları, sezon,
 *    varsayılan pazar ve kaynak kodu.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_rate_codes', function (Blueprint $table) {
            $table->string('folio_text', 40)->nullable()->after('name');                                  // F: Foliotext
            $table->foreignId('weekend_transaction_code_id')->nullable()->after('room_transaction_code_id')->constrained('pms_transaction_codes');
            $table->unsignedSmallInteger('min_advance_days')->nullable()->after('max_los');               // F: Adv. booking days
            $table->unsignedSmallInteger('max_advance_days')->nullable()->after('min_advance_days');
            $table->unsignedTinyInteger('weekend_days')->default(0)->after('max_advance_days');           // Pzt=1 … Paz=64
            $table->boolean('refresh_exchange_at_checkin')->default(true)->after('currency_code');        // F: Refresh at check in
            $table->boolean('is_fixed_rate')->default(false)->after('is_negotiated');
            $table->boolean('is_flat_rate')->default(false)->after('is_fixed_rate');
            $table->boolean('print_rate')->default(false)->after('is_flat_rate');
        });
        Schema::table('pms_rate_amounts', function (Blueprint $table) {
            $table->foreignId('room_type_id')->nullable()->change();
            $table->foreignId('season_id')->nullable()->after('rate_code_id')->constrained('pms_seasons');
            $table->foreignId('market_segment_id')->nullable()->after('weekdays')->constrained('pms_market_segments');
            $table->foreignId('source_code_id')->nullable()->after('market_segment_id')->constrained('pms_source_codes');
            $table->string('room_numbers', 250)->nullable()->after('source_code_id');                    // F: Room numbers "1322,2410"
        });
        Schema::create('pms_rate_amount_room_types', function (Blueprint $table) {
            $table->foreignId('rate_amount_id')->constrained('pms_rate_amounts')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('pms_room_types')->cascadeOnDelete();
            $table->primary(['rate_amount_id', 'room_type_id']);
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_rate_amount_room_types');
        (new ProisvViews)->drop();
        Schema::table('pms_rate_amounts', function (Blueprint $table) {
            foreach (['season_id', 'market_segment_id', 'source_code_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn('room_numbers');
        });
        Schema::table('pms_rate_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekend_transaction_code_id');
            $table->dropColumn(['folio_text', 'min_advance_days', 'max_advance_days', 'weekend_days', 'refresh_exchange_at_checkin', 'is_fixed_rate', 'is_flat_rate', 'print_rate']);
        });
        (new ProisvViews)->create();
    }
};
