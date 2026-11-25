<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N5. Rezervasyon hizmetleri — ikramlar, transfer, ödünç eşya, misafir mesajları, uyandırma, bagaj.
 * Tasarım: docs/03-veritabani.md bölüm N5. Fidelio: Amenities, Guest Transportation, Miscellaneous Items, Messages, Wake Up, Luggage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->decimal('price', 14, 2)->nullable();
            $table->string('department', 20)->default('housekeeping'); // housekeeping / fnb / front_office
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_reservation_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('pms_amenities');
            $table->date('deliver_date');
            $table->string('cycle', 20)->default('once'); // once / daily / every_n_days
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('status', 20)->default('pending'); // pending / delivered / cancelled
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_transport_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes'); // 520 Transfer
            $table->decimal('price', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_reservation_transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('direction', 10);                  // arrival / departure
            $table->foreignId('transport_type_id')->constrained('pms_transport_types');
            $table->timestamp('pickup_at')->nullable();
            $table->string('location', 100)->nullable();      // Ercan, Larnaka …
            $table->string('flight_no', 20)->nullable();
            $table->unsignedSmallInteger('persons')->default(1);
            $table->string('status', 20)->default('requested'); // requested / confirmed / done / cancelled
            $table->unsignedBigInteger('posting_id')->nullable()->index(); // FK: kasa aşaması
            $table->timestamps();
        });

        Schema::create('pms_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->unsignedSmallInteger('quantity_total')->default(1);
            $table->foreignId('transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->decimal('price', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('pms_items');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamps();
            $table->index(['item_id', 'date_from', 'date_to']);
        });

        Schema::create('pms_guest_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->string('from_name', 150)->nullable();
            $table->text('message');
            $table->string('channel', 10)->default('desk'); // desk / phone / sms / email
            $table->timestamp('received_at')->useCurrent();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_wakeup_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->constrained('pms_reservations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('pms_rooms');
            $table->timestamp('call_at');
            $table->string('status', 20)->default('pending'); // pending / done / failed / cancelled
            $table->string('source', 10)->default('manual');  // manual / pbx
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'call_at', 'status']);
        });

        Schema::create('pms_luggage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('reservation_id')->nullable()->constrained('pms_reservations');
            $table->foreignId('profile_id')->nullable()->constrained('pms_profiles');
            $table->string('tag_number', 20);
            $table->unsignedSmallInteger('pieces')->default(1);
            $table->string('location', 100)->nullable();
            $table->timestamp('stored_at')->useCurrent();
            $table->foreignId('stored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_luggage', 'pms_wakeup_calls', 'pms_guest_messages', 'pms_reservation_items', 'pms_items',
            'pms_reservation_transports', 'pms_transport_types', 'pms_reservation_amenities', 'pms_amenities',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
