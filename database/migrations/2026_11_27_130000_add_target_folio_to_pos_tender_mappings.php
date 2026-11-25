<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oda hesabı dışındaki Simphony ödemeleri Fidelio'daki gibi bir sanal hesaba (FA) yazılır: satış kalemleri + ödeme kodu
 * (ör. ödeme tipi 231 → 9902 POS hesabı, 910 Visa Card). Hedef hesap burada tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_pos_tender_mappings', function (Blueprint $table) {
            $table->foreignId('target_folio_id')->nullable()->after('transaction_code_id')->constrained('pms_folios');
        });
    }

    public function down(): void
    {
        Schema::table('pms_pos_tender_mappings', fn (Blueprint $table) => $table->dropConstrainedForeignId('target_folio_id'));
    }
};
