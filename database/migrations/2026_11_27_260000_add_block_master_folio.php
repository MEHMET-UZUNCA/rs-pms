<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Grup ana hesabı (master): blok üyelerinin oda ve pansiyon ücretlerinin yönlendirildiği rezervasyonsuz hesap. */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();   // SQLite tabloyu yeniden kurar; görünümler önce kaldırılır
        Schema::table('pms_blocks', function (Blueprint $table) {
            $table->foreignId('master_folio_id')->nullable()->after('master_reservation_id')->constrained('pms_folios')->nullOnDelete();
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('pms_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_folio_id');
        });
        (new ProisvViews)->create();
    }
};
