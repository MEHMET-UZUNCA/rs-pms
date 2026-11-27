<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Misafir istekleri / şikâyetler (Fidelio Traces, Elektraweb görev yönetimi): tür, öncelik, oda, durum (açık / işlemde / tamam / iptal),
 * son saat trace_date + trace_time; işlem geçmişi pms_trace_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_traces', function (Blueprint $table) {
            $table->string('kind', 20)->default('request')->after('department');   // request / complaint
            $table->string('priority', 10)->default('normal')->after('kind');      // normal / high
            $table->foreignId('room_id')->nullable()->after('priority')->constrained('pms_rooms');
            $table->string('status', 20)->default('open')->after('message');       // open / in_progress / done / cancelled
            $table->string('guest_name', 150)->nullable()->after('room_id');
            $table->index(['property_id', 'status'], 'pms_traces_status_lookup');
        });
        Schema::create('pms_trace_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trace_id')->constrained('pms_traces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_trace_logs');
        Schema::table('pms_traces', function (Blueprint $table) {
            $table->dropIndex('pms_traces_status_lookup');
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn(['kind', 'priority', 'status', 'guest_name']);
        });
    }
};
