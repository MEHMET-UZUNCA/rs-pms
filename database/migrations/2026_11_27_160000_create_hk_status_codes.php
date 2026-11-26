<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "Housekeeping" kodları (INS, CL, DI, TU, DND, NL, SO, NS, DBL). Temizlik durumu kodları odanın hk_status'una karşılık gelir
 * (maps_to); işaret kodları (DND, Sleep Out …) odada hk_flag olarak ayrıca tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_hk_status_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 60);
            $table->string('kind', 10);                     // status / flag
            $table->string('maps_to', 20)->nullable();      // status için: clean / dirty / inspected / pickup
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });
        (new ProisvViews)->drop();
        Schema::table('pms_rooms', function (Blueprint $table) {
            $table->string('hk_flag', 10)->nullable()->after('hk_status');   // F: DND / NL / SO / NS / DBL
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_rooms', fn (Blueprint $table) => $table->dropColumn('hk_flag'));
        (new ProisvViews)->create();
        Schema::dropIfExists('pms_hk_status_codes');
    }
};
