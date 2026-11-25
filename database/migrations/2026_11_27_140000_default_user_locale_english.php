<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Varsayılan arayüz dili İngilizce (Fidelio Suite8 terimleri); kullanıcı Türkçeyi seçebilir. Var olan kullanıcıların seçimi korunur. */
return new class extends Migration
{
    public function up(): void
    {
        // PROISV view'ları users tablosuna dayanır; SQLite tabloyu yeniden kurarken bozulmasın diye kaldırılıp geri kurulur.
        (new ProisvViews)->drop();
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->default('en')->change());
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->default('tr')->change());
        (new ProisvViews)->create();
    }
};
