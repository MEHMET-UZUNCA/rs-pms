<?php

namespace Tests\Feature\Http;

use App\Domain\Profiles\Models\Profile;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class PrivacyTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'a@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    public function test_anonymise_on_request_keeps_stays_removes_personal_data(): void
    {
        $guest = Profile::where('last_name', 'Yılmaz')->firstOrFail();   // içeride
        $this->actingAs($this->user)->post("/profiller/{$guest->id}/anonim")->assertSessionHas('error');   // güncel rezervasyon var

        $past = Profile::create(['property_id' => $this->property, 'type' => 'individual', 'last_name' => 'Eski', 'first_name' => 'Misafir', 'display_name' => 'Eski, Misafir',
            'birth_date' => '1970-01-01', 'tax_number' => '12345678901', 'status' => 'active']);
        $past->communications()->create(['type' => 'email', 'value' => 'eski@example.com', 'value_normalized' => 'eski@example.com']);
        $past->addresses()->create(['type' => 'home', 'line1' => 'Sokak 1', 'city' => 'Girne', 'is_primary' => true]);
        $resId = DB::table('pms_reservations')->where('status', 'checked_out')->value('id');
        DB::table('pms_reservations')->where('id', $resId)->update(['profile_id' => $past->id]);

        $this->actingAs($this->user)->get("/profiller/{$past->id}/duzenle")->assertSee('Anonymise (KVKK)');
        $this->actingAs($this->user)->post("/profiller/{$past->id}/anonim")->assertSessionHas('status');
        $past->refresh();
        $this->assertSame(['Anonim', null, null, null, 'Anonim #'.$past->id], [$past->last_name, $past->first_name, $past->birth_date, $past->tax_number, $past->display_name]);
        $this->assertSame(0, $past->communications()->count() + $past->addresses()->count());
        $this->assertSame($past->id, (int) DB::table('pms_reservations')->where('id', $resId)->value('profile_id'));   // konaklama kalır
        $this->assertTrue(DB::table('pms_audit_logs')->where('auditable_type', 'profile')->where('auditable_id', $past->id)->where('action', 'anonymized')->exists());
        $this->assertSame(0, DB::table('pms_audit_logs')->where('auditable_type', 'profile')->where('auditable_id', $past->id)->where('new_values', 'like', '%Eski%')->count());
    }

    public function test_retention_command(): void
    {
        $old = Profile::create(['property_id' => $this->property, 'type' => 'individual', 'last_name' => 'Çokeski', 'display_name' => 'Çokeski', 'status' => 'active']);
        DB::table('pms_profiles')->where('id', $old->id)->update(['created_at' => now()->subYears(6)]);

        Artisan::call('pms:anonymize-profiles');
        $this->assertStringContainsString('atlandı', Artisan::output());   // süre ayarlanmamış
        ReservationOptions::save($this->property, ['privacy_retention_years' => 5], null);
        Artisan::call('pms:anonymize-profiles', ['--dry-run' => true]);
        $this->assertSame('Çokeski', $old->fresh()->last_name);
        Artisan::call('pms:anonymize-profiles');
        $this->assertSame('Anonim', $old->fresh()->last_name);
        $this->assertSame('Yılmaz', Profile::where('last_name', 'Yılmaz')->value('last_name'));   // yeni / güncel misafire dokunulmaz
    }
}
