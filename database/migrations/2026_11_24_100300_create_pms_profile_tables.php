<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D. Profiller — misafir, şirket, acente, kaynak, grup, tedarikçi, toplantı organizatörü ve alt tabloları.
 * Tasarım: docs/03-veritabani.md bölüm D. Fidelio: PRO_ISV_PROFILEINFO, PRO_ISV_IND_FAM_LINKS, PRO_ISV_MEMBERCARDINFO, PRO_GIYKIMBIL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('pms_properties'); // null: tüm tesislerde ortak
            $table->string('type', 20);    // individual / company / travel_agent / source / group / vendor / meeting_planner / contact
            $table->string('status', 20)->default('active'); // active / inactive / terminated / merged / blacklisted
            $table->string('title', 20)->nullable();
            $table->string('salutation', 30)->nullable();      // F: ADDRESSGREETING
            $table->string('letter_greeting', 60)->nullable(); // F: LETTERGREETING
            $table->string('first_name', 100)->nullable();     // F: NAME2
            $table->string('middle_name', 100)->nullable();    // F: NAME3
            $table->string('last_name', 100)->nullable();      // F: NAME1 (bireysel)
            $table->string('company_name', 200)->nullable();   // F: NAME1 (şirket/acente/grup)
            $table->string('display_name', 250);               // F: GUESTFULLNAME
            $table->string('gender', 10)->nullable();          // F: SEX 1/2/3
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('father_name', 60)->nullable();     // F: BABAADI
            $table->string('mother_name', 60)->nullable();     // F: ANAADI
            $table->string('marital_status', 20)->nullable(); // F: MEDENIHALI
            $table->string('occupation', 100)->nullable();     // F: OCCUPATION / ISI
            $table->date('wedding_date')->nullable();          // F: WEDDINGDAY
            $table->char('nationality_code', 2)->nullable();
            $table->char('language_code', 2)->nullable();
            $table->foreignId('vip_level_id')->nullable()->constrained('pms_vip_levels');
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 30)->nullable()->index();
            $table->string('iata_code', 20)->nullable();
            $table->decimal('commission_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('commission_code_id')->nullable()->index();   // FK: komisyon aşaması
            $table->unsignedBigInteger('ar_account_id')->nullable()->index();        // FK: cari aşaması
            $table->unsignedBigInteger('default_rate_code_id')->nullable()->index(); // FK: fiyat aşaması
            $table->foreignId('default_market_segment_id')->nullable()->constrained('pms_market_segments');
            $table->foreignId('default_source_code_id')->nullable()->constrained('pms_source_codes');
            $table->string('blacklist_reason', 500)->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('privacy_notice_at')->nullable();
            $table->boolean('no_mailing')->default(false);         // F: NOMAILING
            $table->boolean('no_emailing')->default(false);        // F: NOEMAILING
            $table->boolean('store_private_data')->default(true);  // F: STOREPRIVATEDATA
            $table->unsignedBigInteger('rsone_client_id')->nullable()->index(); // RS ONE clients.id
            $table->foreignId('merged_into_id')->nullable()->constrained('pms_profiles'); // F: MERGEDTO_PROFILE_ID
            $table->timestamp('merged_at')->nullable();            // F: MERGETIME
            $table->foreignId('primary_contact_profile_id')->nullable()->constrained('pms_profiles'); // F: PRIMARY_CONTACT_ID
            $table->string('origin_hotel_name', 200)->nullable();  // F: ORIGINATED_HOTEL_NAME
            $table->string('origin_hotel_legacy_id', 50)->nullable(); // F: ORIGINATED_HOTEL_ID
            $table->string('legacy_system', 20)->nullable();
            $table->string('legacy_id', 50)->nullable();           // F: PROFILE_ID
            $table->string('legacy_global_id', 50)->nullable();    // F: PROFILE_GLOBALID
            $table->string('legacy_created_by', 100)->nullable();
            $table->string('legacy_updated_by', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('nationality_code')->references('code')->on('pms_countries');
            $table->index(['property_id', 'type', 'display_name']);
            $table->index(['last_name', 'first_name']);
            $table->index(['legacy_system', 'legacy_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pms_profiles ADD FULLTEXT pms_profiles_display_name_fulltext (display_name)');
        }

        Schema::create('pms_profile_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->string('type', 20)->default('home'); // home / business / billing
            $table->string('line1', 200)->nullable();
            $table->string('line2', 200)->nullable();
            $table->string('line3', 200)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('postal_code', 15)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('pms_countries');
        });

        Schema::create('pms_profile_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->string('type', 20); // mobile / phone / email / fax / web
            $table->string('value', 190);
            $table->string('value_normalized', 190)->nullable()->index(); // E.164 telefon / küçük harf e-posta
            $table->boolean('is_primary')->default(false);
            $table->boolean('marketing_allowed')->default(false);
            $table->timestamps();
        });

        Schema::create('pms_profile_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->string('document_type', 20);       // kktc_id / tc_id / passport / id_card / driving_license
            $table->text('document_number');           // şifreli
            $table->char('document_number_hash', 64)->index();
            $table->string('serial_number', 60)->nullable(); // F: KIMLIKSERINO
            $table->char('issuing_country', 2)->nullable();
            $table->string('issue_place', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('registry')->nullable();      // nüfus kaydı: il, ilçe, mahalle, cilt, aile sıra, sıra no
            $table->string('file_path', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_profile_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->foreignId('related_profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->string('relation', 30); // employee_of / booker_for / family / spouse / child / assistant — F: LINK_TYPE
            $table->timestamps();
            $table->unique(['profile_id', 'related_profile_id', 'relation'], 'pms_profile_relations_unique');
        });

        Schema::create('pms_profile_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->foreignId('special_request_id')->constrained('pms_special_requests');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pms_membership_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 3);    // F: SHORTDESC
            $table->string('name', 100);  // F: LONGDESC
            $table->string('role', 20)->default('loyalty'); // ar_number / iata / vat / loyalty / corporate_id / other — F: CARDROLE
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_profile_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained('pms_membership_types');
            $table->string('number', 100); // F: CARDVALUE
            $table->string('level', 30)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_profile_negotiated_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pms_profiles')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->unsignedBigInteger('rate_code_id')->index(); // FK: fiyat aşaması
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_note_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->string('code', 10);
            $table->string('name', 100);
            $table->string('area', 20)->default('general'); // general / front_office / housekeeping / accounting / sales
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('pms_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('pms_properties');
            $table->morphs('notable'); // profil / rezervasyon / blok / folyo / cari / etkinlik
            $table->foreignId('note_category_id')->nullable()->constrained('pms_note_categories');
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->boolean('show_on_check_in')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pms_profile_stats', function (Blueprint $table) {
            $table->foreignId('profile_id')->primary()->constrained('pms_profiles')->cascadeOnDelete();
            $table->date('first_stay_date')->nullable();
            $table->date('last_stay_date')->nullable();
            $table->unsignedInteger('stays')->default(0);
            $table->unsignedInteger('nights')->default(0);
            $table->decimal('room_revenue', 14, 2)->default(0);
            $table->decimal('other_revenue', 14, 2)->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->unsignedInteger('cancellations')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'pms_profile_stats', 'pms_notes', 'pms_note_categories', 'pms_profile_negotiated_rates',
            'pms_profile_memberships', 'pms_membership_types', 'pms_profile_preferences', 'pms_profile_relations',
            'pms_profile_identities', 'pms_profile_communications', 'pms_profile_addresses', 'pms_profiles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
