<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B. Oda envanteri — bina, kat, oda sınıfı (Fidelio otel segmenti), oda tipi, oda, özellik, bağlantı, arıza, görsel.
 * Tasarım: docs/03-veritabani.md bölüm B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('building_id')->nullable()->constrained('pms_buildings');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'building_id', 'code']);
        });

        Schema::create('pms_room_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);                 // F: HOTELSEGMENT
            $table->string('name', 100);                // F: YCAD_LONGDESC
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 50)->nullable(); // F: YCAD_ID
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_class_id')->nullable()->constrained('pms_room_classes');
            $table->string('code', 10);                 // F: YCAT_SHORTDESC
            $table->string('name', 100);                // F: YCAT_LONGDESC
            $table->string('short_name', 30)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('default_adults')->default(2);
            $table->unsignedTinyInteger('max_adults')->default(2);
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->unsignedTinyInteger('max_extra_beds')->default(0);
            $table->string('bed_type', 30)->nullable();
            $table->decimal('size_m2', 6, 2)->nullable();
            $table->string('view', 50)->nullable();
            $table->char('color', 7)->nullable();
            $table->boolean('is_pseudo')->default(false);   // F: YCAT_NOTINAVL
            $table->unsignedTinyInteger('beds')->default(2); // F: NOOFBEDS
            $table->boolean('is_bookable_online')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();    // F: YCAT_ID
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_type_id')->constrained('pms_room_types');
            $table->foreignId('building_id')->nullable()->constrained('pms_buildings');
            $table->foreignId('floor_id')->nullable()->constrained('pms_floors');
            $table->unsignedBigInteger('hk_section_id')->nullable()->index(); // FK: kat hizmetleri aşaması
            $table->string('number', 10);
            $table->string('name', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('max_occupancy')->nullable();
            $table->decimal('hk_credits', 4, 1)->default(1);
            $table->boolean('is_pseudo')->default(false);
            $table->boolean('is_smoking')->default(false);
            $table->boolean('is_accessible')->default(false);
            $table->string('fo_status', 20)->default('vacant');          // vacant / occupied
            $table->string('hk_status', 20)->default('clean');           // clean / dirty / inspected / pickup
            $table->string('service_status', 20)->default('in_service'); // in_service / out_of_order / out_of_service
            $table->unsignedBigInteger('current_reservation_id')->nullable()->index(); // FK: rezervasyon aşaması
            $table->timestamp('hk_status_changed_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'number']);
            $table->index(['property_id', 'room_type_id']);
            $table->index(['property_id', 'hk_status']);
        });

        Schema::create('pms_room_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_room_feature_room', function (Blueprint $table) {
            $table->foreignId('room_id')->constrained('pms_rooms')->cascadeOnDelete();
            $table->foreignId('room_feature_id')->constrained('pms_room_features')->cascadeOnDelete();
            $table->primary(['room_id', 'room_feature_id']);
        });

        Schema::create('pms_room_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('pms_rooms')->cascadeOnDelete();
            $table->foreignId('connected_room_id')->constrained('pms_rooms')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['room_id', 'connected_room_id']);
        });

        Schema::create('pms_room_outages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->string('type', 5);                 // ooo / oos
            $table->date('date_from');
            $table->date('date_to');                   // hariç
            $table->foreignId('reason_code_id')->constrained('pms_reason_codes');
            $table->string('note', 500)->nullable();
            $table->string('return_hk_status', 20)->default('dirty');
            $table->unsignedBigInteger('rsone_task_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'date_from', 'date_to']);
        });

        Schema::create('pms_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->morphs('mediable');
            $table->string('path', 500);
            $table->string('category', 40)->nullable();
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_media', 'pms_room_outages', 'pms_room_connections', 'pms_room_feature_room', 'pms_room_features',
            'pms_rooms', 'pms_room_types', 'pms_room_classes', 'pms_floors', 'pms_buildings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
