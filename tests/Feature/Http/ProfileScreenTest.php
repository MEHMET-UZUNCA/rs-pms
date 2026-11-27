<?php

namespace Tests\Feature\Http;

use App\Domain\Profiles\Models\Profile;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ProfileScreenTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = $this->makeUser('admin');
    }

    private function makeUser(string $role): User
    {
        $user = User::create(['username' => 'U'.$role, 'name' => $role, 'email' => strtolower($role).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', $role)->value('id')]);

        return $user;
    }

    public function test_navigator_tabs_search_and_summary_with_history(): void
    {
        $html = $this->actingAs($this->user)->get('/profiller')->assertOk()->getContent();
        foreach (['Profiles', 'Individual', 'Company', 'Travel Agent', 'Yılmaz, Ahmet'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->actingAs($this->user)->get('/profiller?ad=yılmaz')->assertSee('Yılmaz, Ahmet')->assertDontSee('Smith, John');
        $this->actingAs($this->user)->get('/profiller?iletisim=533 000 00 11')->assertSee('Yılmaz, Ahmet');   // telefonla
        $this->actingAs($this->user)->get('/profiller?tur=company')->assertSee('Örnek Holding A.Ş.');

        $yilmaz = Profile::where('last_name', 'Yılmaz')->firstOrFail();
        $this->actingAs($this->user)->get("/profiller/{$yilmaz->id}/ozet")->assertOk()->assertSee('Stay history')->assertSee('In House');
        $company = Profile::where('display_name', 'Örnek Holding A.Ş.')->firstOrFail();
        $this->actingAs($this->user)->get("/profiller/{$company->id}/ozet")->assertOk()->assertSee('Stay history');
    }

    public function test_new_individual_profile_with_contact_and_address(): void
    {
        $this->actingAs($this->user)->post('/profiller/yeni/individual', [
            'last_name' => 'Özcan', 'first_name' => 'Murat Serdar', 'title' => 'Bay', 'gender' => 'male', 'birth_date' => '15.04.1980',
            'nationality_code' => 'TR', 'language_code' => 'tr', 'mobile' => '+90 533 123 45 67', 'email' => 'murat@example.com',
            'address' => ['line1' => 'Kordonboyu 20', 'city' => 'Girne', 'country_code' => 'CY'], 'status' => 'active', 'marketing_consent' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $p = Profile::where('last_name', 'Özcan')->firstOrFail();
        $this->assertSame(['Özcan, Murat Serdar', 'individual', '1980-04-15', 'tr'], [$p->display_name, $p->type, $p->birth_date->toDateString(), $p->language_code]);
        $this->assertTrue((bool) $p->marketing_consent);
        $this->assertNotNull($p->marketing_consent_at);
        $this->assertSame('Girne', $p->addresses()->value('city'));
        $this->assertSame('+905331234567', $p->communications()->where('type', 'mobile')->value('value_normalized'));

        // Düzenle: e-posta silinir, kara liste nedeni zorunlu
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'first_name' => 'Murat', 'status' => 'blacklisted'])->assertSessionHasErrors('blacklist_reason');
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'first_name' => 'Murat', 'status' => 'blacklisted', 'blacklist_reason' => 'Unpaid bill'])
            ->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(['Özcan, Murat', 'blacklisted', 0, 0], [$p->display_name, $p->status, $p->communications()->count(), $p->addresses()->count()]);
        $this->actingAs($this->user)->get('/profiller?ad=Özcan')->assertSee('Blacklisted');
    }

    public function test_new_company_and_validation(): void
    {
        $this->actingAs($this->user)->post('/profiller/yeni/travel_agent', ['company_name' => 'Kıbrıs Tur', 'tax_number' => '1234567890', 'iata_code' => '12345678',
            'commission_pct' => '10,5', 'default_market_segment_id' => DB::table('pms_market_segments')->where('code', 'TA')->value('id'), 'status' => 'active'])->assertSessionHasNoErrors();
        $agent = Profile::where('display_name', 'Kıbrıs Tur')->firstOrFail();
        $this->assertSame(['travel_agent', '10.50'], [$agent->type, (string) $agent->commission_pct]);
        $this->actingAs($this->user)->get('/profiller?tur=travel_agent&ad=12345678')->assertSee('Kıbrıs Tur');   // IATA ile

        $this->actingAs($this->user)->post('/profiller/yeni/company', ['status' => 'active'])->assertSessionHasErrors('company_name');
        $this->actingAs($this->user)->post('/profiller/yeni/individual', ['status' => 'active', 'email' => 'x'])->assertSessionHasErrors(['last_name', 'email']);
        $this->actingAs($this->user)->get('/profiller/yeni/nope')->assertNotFound();
    }

    public function test_permissions(): void
    {
        $acc = $this->makeUser('ACC');   // görür, düzenleyemez
        $this->actingAs($acc)->get('/profiller')->assertOk()->assertDontSee(route('profiles.create', 'individual'), false);
        $this->actingAs($acc)->get('/profiller/yeni/individual')->assertForbidden();
        $this->actingAs($this->makeUser('HKS'))->get('/profiller')->assertForbidden();
    }
}
