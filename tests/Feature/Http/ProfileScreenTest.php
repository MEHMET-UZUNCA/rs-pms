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

    public function test_new_profile_asks_type_first(): void
    {
        $html = $this->actingAs($this->user)->get('/profiller?tur=company')->assertOk()->getContent();
        $this->assertStringContainsString('Select Profile Type', $html);
        $this->assertMatchesRegularExpression('/<option value="company" selected>Company</', $html);
        $this->actingAs($this->user)->get('/profiller/yeni?tur=travel_agent')->assertRedirect('/profiller/yeni/travel_agent');
        $this->actingAs($this->user)->get('/profiller/yeni?tur=vendor')->assertNotFound();

        $form = $this->actingAs($this->user)->get('/profiller/yeni/individual')->assertOk()->getContent();
        foreach (['Position', 'Department', 'Letter greeting', 'Active?', 'Birthday / Place', 'More Fields', 'VIP Code', 'Wedding day', 'Personal Documents'] as $label) {
            $this->assertStringContainsString($label, $form);
        }
        $this->assertStringContainsString('Blacklist reason', $form);
        $this->assertStringNotContainsString('Marketing consent', $form);
        $this->assertStringNotContainsString('Occupation', $form);
    }

    public function test_new_individual_profile_with_contact_address_and_documents(): void
    {
        $this->actingAs($this->user)->post('/profiller/yeni/individual', [
            'last_name' => 'Özcan', 'first_name' => 'Murat Serdar', 'title' => 'Bay', 'gender' => 'male', 'birth_date' => '15.04.1980', 'birth_place' => 'Lefkoşa',
            'position' => 'Director', 'department' => 'Sales', 'salutation' => 'Mr. Özcan', 'letter_greeting' => 'Dear Mr. Özcan', 'wedding_date' => '20.06.2010',
            'nationality_code' => 'TR', 'language_code' => 'tr', 'mobile' => '+90 533 123 45 67', 'email' => 'murat@example.com',
            'address' => ['line1' => 'Kordonboyu 20', 'city' => 'Girne', 'country_code' => 'CY'], 'active' => '1', 'store_private_data' => '1',
            'documents_sent' => '1', 'documents' => [['type' => 'passport', 'number' => 'U 1234567'], ['type' => '', 'number' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $p = Profile::where('last_name', 'Özcan')->firstOrFail();
        $this->assertSame(['Özcan, Murat Serdar', 'individual', '1980-04-15', 'tr', 'active', 'Director', 'Sales', 'Lefkoşa', '2010-06-20'],
            [$p->display_name, $p->type, $p->birth_date->toDateString(), $p->language_code, $p->status, $p->position, $p->department, $p->birth_place, $p->wedding_date->toDateString()]);
        $this->assertSame('Girne', $p->addresses()->value('city'));
        $this->assertSame('+905331234567', $p->communications()->where('type', 'mobile')->value('value_normalized'));
        $doc = DB::table('pms_profile_identities')->where('profile_id', $p->id)->sole();
        $this->assertSame('passport', $doc->document_type);
        $this->assertStringNotContainsString('1234567', $doc->document_number);   // şifreli
        $this->assertSame(\App\Domain\Profiles\Services\ProfileService::documentHash('u1234567'), $doc->document_number_hash);
        $this->actingAs($this->user)->get("/profiller/{$p->id}/duzenle")->assertOk()->assertSee('U 1234567')->assertSee('Lefkoşa');

        // Düzenle: pasif, belge numarası değişir, e-posta ve adres silinir; belge tipi zorunlu
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'documents_sent' => '1', 'documents' => [['number' => '123']]])
            ->assertSessionHasErrors('documents.0.type');
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'first_name' => 'Murat', 'active' => '0',
            'documents_sent' => '1', 'documents' => [['id' => $doc->id, 'type' => 'kktc_id', 'number' => '999']]])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(['Özcan, Murat', 'inactive', 0, 0], [$p->display_name, $p->status, $p->communications()->count(), $p->addresses()->count()]);
        $this->assertSame([$doc->id, 'kktc_id'], [(int) DB::table('pms_profile_identities')->where('profile_id', $p->id)->value('id'), DB::table('pms_profile_identities')->where('profile_id', $p->id)->value('document_type')]);

        // Kara liste: neden zorunlu; belgelerin hepsi kaldırılırsa silinir
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'active' => '1', 'blacklisted' => '1'])->assertSessionHasErrors('blacklist_reason');
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'active' => '1', 'blacklisted' => '1', 'blacklist_reason' => 'Unpaid bill', 'documents_sent' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['blacklisted', 'Unpaid bill', 0], [$p->fresh()->status, $p->fresh()->blacklist_reason, DB::table('pms_profile_identities')->where('profile_id', $p->id)->count()]);
        $this->actingAs($this->user)->get('/profiller?ad=Özcan')->assertSee('Blacklisted');
        $this->actingAs($this->user)->get("/profiller/{$p->id}/duzenle")->assertSee('value="Unpaid bill"', false);
        $this->actingAs($this->user)->get("/profiller/{$p->id}/ozet")->assertSee('Unpaid bill');

        // Rezervasyonda profil aramasında kara liste ve nedeni gelir; rezervasyon kartında uyarı
        $found = $this->actingAs($this->user)->getJson('/rezervasyon/profiller?q=Özcan')->assertOk()->json();
        $this->assertSame(['blacklisted', 'Unpaid bill'], [$found[0]['status'], $found[0]['blacklist_reason']]);
        $res = \App\Domain\Reservations\Models\Reservation::query()->where('property_id', $this->property)->firstOrFail();
        $res->update(['profile_id' => $p->id]);
        $this->actingAs($this->user)->get(route('reservations.edit', $res))->assertOk()->assertSee('Blacklisted:')->assertSee('Unpaid bill');

        // Kara listeden çıkınca neden silinir
        $this->actingAs($this->user)->put("/profiller/{$p->id}", ['last_name' => 'Özcan', 'active' => '1', 'blacklisted' => '0', 'blacklist_reason' => 'Unpaid bill'])->assertSessionHasNoErrors();
        $this->assertSame(['active', null], [$p->fresh()->status, $p->fresh()->blacklist_reason]);
    }

    public function test_new_company_and_validation(): void
    {
        $this->actingAs($this->user)->post('/profiller/yeni/travel_agent', ['company_name' => 'Kıbrıs Tur', 'tax_number' => '1234567890', 'iata_code' => '12345678',
            'commission_pct' => '10,5', 'default_market_segment_id' => DB::table('pms_market_segments')->where('code', 'TA')->value('id'), 'active' => '1'])->assertSessionHasNoErrors();
        $agent = Profile::where('display_name', 'Kıbrıs Tur')->firstOrFail();
        $this->assertSame(['travel_agent', '10.50', 'active'], [$agent->type, (string) $agent->commission_pct, $agent->status]);
        $this->actingAs($this->user)->get('/profiller?tur=travel_agent&ad=12345678')->assertSee('Kıbrıs Tur');   // IATA ile

        $this->actingAs($this->user)->post('/profiller/yeni/company', ['status' => 'active'])->assertSessionHasErrors('company_name');
        $this->actingAs($this->user)->post('/profiller/yeni/individual', ['status' => 'active', 'email' => 'x'])->assertSessionHasErrors(['last_name', 'email']);
        $this->actingAs($this->user)->get('/profiller/yeni/nope')->assertNotFound();
    }

    public function test_permissions(): void
    {
        $acc = $this->makeUser('ACC');   // görür, düzenleyemez
        $this->actingAs($acc)->get('/profiller')->assertOk()->assertDontSee('Select Profile Type');
        $this->actingAs($acc)->get('/profiller/yeni/individual')->assertForbidden();
        $this->actingAs($this->makeUser('HKS'))->get('/profiller')->assertForbidden();
    }
}
