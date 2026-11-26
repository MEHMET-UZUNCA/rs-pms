<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multiboard (çoklu pansiyon): pansiyon tipinin öğünleri (kahvaltı / öğle / akşam) ve rezervasyonun her gecesinin pansiyonu.
 * Gece pansiyonu başlangıçta fiyat kodundaki pansiyondur; kalış ortasında değiştirilebilir (Meal Plan sekmesi).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_packages', function (Blueprint $table) {
            $table->boolean('includes_breakfast')->default(false)->after('type');
            $table->boolean('includes_lunch')->default(false)->after('includes_breakfast');
            $table->boolean('includes_dinner')->default(false)->after('includes_lunch');
        });
        Schema::table('pms_reservation_nights', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('rate_code_id')->constrained('pms_packages');   // gecenin pansiyonu
        });
        (new ProisvViews)->create();

        // Mevcut geceler: fiyat kodunun pansiyonu
        foreach (DB::table('pms_rate_codes')->whereNotNull('package_id')->get(['id', 'package_id']) as $rate) {
            DB::table('pms_reservation_nights')->where('rate_code_id', $rate->id)->whereNull('package_id')->update(['package_id' => $rate->package_id]);
        }
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_reservation_nights', fn (Blueprint $table) => $table->dropConstrainedForeignId('package_id'));
        Schema::table('pms_packages', fn (Blueprint $table) => $table->dropColumn(['includes_breakfast', 'includes_lunch', 'includes_dinner']));
        (new ProisvViews)->create();
    }
};
