<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G (kısmi). İşlem kodları — Fidelio departman kodları (100 Room, 320 FOOD Cafe Rocks, 900 Cash …).
 * Fiyat kodları ve paketler bu kodlara bağlandığı için 2. aşamada oluşturulur.
 * Tasarım: docs/03-veritabani.md bölüm G; canlı kod listesi: docs/fidelio/eslesme.md §15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_transaction_code_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('parent_id')->nullable()->constrained('pms_transaction_code_groups');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('type', 20)->default('revenue'); // revenue / payment / non_revenue
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_transaction_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->foreignId('group_id')->constrained('pms_transaction_code_groups');
            $table->string('code', 10);                    // F: DC_NUMBER
            $table->string('name', 100);                   // F: DC_DESCRIPTION
            $table->string('type', 20)->default('revenue'); // revenue / payment / paid_out / deposit / ar_transfer / internal
            $table->string('payment_kind', 20)->nullable(); // cash / card / bank_transfer / city_ledger / voucher / foreign_cash
            $table->char('currency_code', 3)->nullable();
            $table->string('revenue_bucket', 20)->nullable(); // room / breakfast / lunch / dinner / allinc_food / allinc_beverage / food / beverage / minibar / telephone / spa / laundry / other
            $table->string('food_beverage', 10)->nullable();  // food / beverage / other
            $table->boolean('is_discount')->default(false);
            $table->foreignId('discount_of_transaction_code_id')->nullable()->constrained('pms_transaction_codes');
            $table->boolean('is_no_show')->default(false);
            $table->boolean('is_tax_inclusive')->default(true);
            $table->decimal('default_price', 14, 2)->nullable();
            $table->boolean('manual_post_allowed')->default(true);
            $table->unsignedBigInteger('outlet_id')->nullable()->index(); // FK: Simphony aşaması
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 50)->nullable();   // F: DC_ID
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('currency_code')->references('code')->on('pms_currencies');
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_transaction_code_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_code_id')->constrained('pms_transaction_codes')->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('pms_taxes');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('calc_base', 20)->default('net'); // net / net_plus_previous
            $table->unique(['transaction_code_id', 'tax_id']);
        });

        Schema::table('pms_properties', function (Blueprint $table) {
            $table->foreign('exchange_diff_transaction_code_id')->references('id')->on('pms_transaction_codes');
        });
    }

    public function down(): void
    {
        Schema::table('pms_properties', function (Blueprint $table) {
            $table->dropForeign(['exchange_diff_transaction_code_id']);
        });
        Schema::dropIfExists('pms_transaction_code_taxes');
        Schema::dropIfExists('pms_transaction_codes');
        Schema::dropIfExists('pms_transaction_code_groups');
    }
};
