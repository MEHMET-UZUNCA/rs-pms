<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rezervasyonun oda tercihleri (Fidelio Reservation → Features): deniz manzarası, sigara içilmeyen, bağlantılı oda … */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_reservation_features', function (Blueprint $table) {
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('room_feature_id')->constrained('pms_room_features')->cascadeOnDelete();
            $table->primary(['reservation_id', 'room_feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_reservation_features');
    }
};
