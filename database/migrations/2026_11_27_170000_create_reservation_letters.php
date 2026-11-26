<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rezervasyona gönderilen mektuplar (onay mektubu e-postası) — kime, hangi dilde, kim gönderdi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_reservation_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('type', 20)->default('confirmation');
            $table->char('language', 2);
            $table->string('email', 150);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['reservation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_reservation_letters');
    }
};
