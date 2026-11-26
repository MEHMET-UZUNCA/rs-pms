<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelio "Lost & Found Items": kayıp (misafirin kaybettiğini bildirdiği) ve bulunan (personelin bulduğu) eşyalar ayrı listelerde;
 * kayıp ile bulunan eşleştirilir (Match), sonra teslim ya da imha ile kapanır. Her eşyanın hareket geçmişi (Action, Entered, User, Note).
 * Grup ve ortak alan listeleri pms_reason_codes'ta (category: lost_found_group, public_place).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_lost_found', function (Blueprint $table) {
            $table->string('kind', 5)->default('found')->after('property_id');                  // lost / found
            $table->string('number', 20)->nullable()->after('kind');                            // F: Number
            $table->foreignId('group_id')->nullable()->after('number')->constrained('pms_reason_codes');        // F: Group
            $table->foreignId('public_place_id')->nullable()->after('group_id')->constrained('pms_reason_codes'); // F: Public places
            $table->string('location', 150)->nullable()->after('public_place_id');             // F: Where
            $table->string('person_name', 150)->nullable()->after('profile_id');               // F: Lost By / Found By (kişi)
            $table->string('contact', 150)->nullable()->after('person_name');                  // telefon / e-posta
            $table->foreignId('matched_id')->nullable()->after('contact')->constrained('pms_lost_found')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('returned_to');                // teslim / imha
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['property_id', 'kind', 'status']);
        });

        Schema::create('pms_lost_found_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_found_id')->constrained('pms_lost_found')->cascadeOnDelete();
            $table->string('action', 20);                    // reported / found / matched / unmatched / returned / disposed / note / stored
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_lost_found_actions');
        Schema::table('pms_lost_found', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'kind', 'status']);
            foreach (['group_id', 'public_place_id', 'matched_id', 'created_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['kind', 'number', 'location', 'person_name', 'contact', 'resolved_at']);
        });
    }
};
