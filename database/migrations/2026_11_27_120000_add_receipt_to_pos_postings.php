<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Simphony FO_FI_FINAL_TENDER ile gelen fiş dökümü (çekin metin hali) — folyoda "bu tutarda ne var" sorusunun cevabı. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_pos_postings', function (Blueprint $table) {
            $table->text('receipt_text')->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('pms_pos_postings', fn (Blueprint $table) => $table->dropColumn('receipt_text'));
    }
};
