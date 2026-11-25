<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C. Kodlar (Fidelio mantığı) — market grubu/segmenti, kaynak, kanal, rezervasyon (garanti) tipi, VIP, özel istek.
 * Tasarım: docs/03-veritabani.md bölüm C.
 */
return new class extends Migration
{
    public function up(): void
    {
        $common = function (Blueprint $table, int $codeLength = 10) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', $codeLength);
            $table->string('name', 100);
        };
        $tail = function (Blueprint $table) {
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        };

        Schema::create('pms_market_groups', function (Blueprint $table) use ($common, $tail) {
            $common($table, 3);                       // F: MAINMARKETCODE
            $tail($table);
        });

        Schema::create('pms_market_segments', function (Blueprint $table) use ($common, $tail) {
            $common($table, 3);                       // F: MARKETCODE
            $table->foreignId('market_group_id')->nullable()->constrained('pms_market_groups');
            $tail($table);
        });

        Schema::create('pms_source_codes', function (Blueprint $table) use ($common, $tail) {
            $common($table, 3);                       // F: SOURCECODE
            $tail($table);
        });

        Schema::create('pms_channel_codes', function (Blueprint $table) use ($common, $tail) {
            $common($table, 3);                       // F: CHANNELCODE
            $table->string('type', 20)->default('direct'); // direct / phone / web / ota / gds / agency / walk_in / rsone
            $tail($table);
        });

        Schema::create('pms_reservation_types', function (Blueprint $table) use ($common, $tail) {
            $common($table, 30);                      // F: GUARANTEECODE
            $table->boolean('deducts_inventory')->default(true);
            $table->boolean('is_guaranteed')->default(false);
            $table->boolean('requires_deposit')->default(false);
            $table->time('release_hour')->nullable();
            $tail($table);
        });

        Schema::create('pms_vip_levels', function (Blueprint $table) use ($common, $tail) {
            $common($table, 3);                       // F: VIPCODE
            $table->char('color', 7)->nullable();
            $tail($table);
        });

        Schema::create('pms_special_requests', function (Blueprint $table) use ($common, $tail) {
            $common($table, 10);
            $table->string('category', 30)->nullable();
            $tail($table);
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_special_requests', 'pms_vip_levels', 'pms_reservation_types', 'pms_channel_codes',
            'pms_source_codes', 'pms_market_segments', 'pms_market_groups',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
