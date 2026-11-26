<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Web API istemcileri (otelin web sitesi, kanal yöneticisi …): anahtarın yalnız SHA-256 özeti saklanır. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 8);                 // anahtarı tanımak için ilk 8 karakter
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_api_clients');
    }
};
