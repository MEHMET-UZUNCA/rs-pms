<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;

/**
 * Fidelio uyumlu PROISV view'ları (13 view, 391 kolon): PRO_ISV_POSTINGS, PRO_ISV_RESERVATIONINFO …
 * Tanımlar: app/Support/Database/ProisvViews.php; kolon karşılıkları: docs/fidelio/kolon-kolon.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
    }
};
