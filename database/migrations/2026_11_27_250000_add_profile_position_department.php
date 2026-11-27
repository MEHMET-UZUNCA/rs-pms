<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fidelio profil kartı: Position ve Department alanları (F: POSITION, DEPARTMENT). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->string('position', 100)->nullable()->after('company_name');
            $table->string('department', 100)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('pms_profiles', function (Blueprint $table) {
            $table->dropColumn(['position', 'department']);
        });
    }
};
