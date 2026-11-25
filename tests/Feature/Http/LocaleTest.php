<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class LocaleTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::create(['username' => 'MU', 'name' => 'M.U', 'email' => 'mu@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    public function test_english_is_default_with_fidelio_terms(): void
    {
        $this->assertSame('en', $this->user->fresh()->locale);
        $this->actingAs($this->user)->get('/rezervasyon')->assertOk()
            ->assertSee('Reservation Navigator')->assertSee('In House')->assertSee('Arrivals')->assertSee('Departures')
            ->assertSee('Check In')->assertSee('Business Date')->assertSee('Front Office')->assertSee('Cashiering')->assertSee('Housekeeping')
            ->assertSee('<html lang="en">', false);
        $this->actingAs($this->user)->get('/ayarlar/departman-kodlari')->assertSee('Department Codes')->assertSee('Show Inactive');
    }

    public function test_user_switches_to_turkish_and_choice_is_kept(): void
    {
        $this->actingAs($this->user)->from('/rezervasyon')->post('/dil/tr')->assertRedirect('/rezervasyon');
        $this->assertSame('tr', $this->user->fresh()->locale);
        $this->actingAs($this->user->fresh())->get('/rezervasyon')->assertSee('Rezervasyon Navigatörü')->assertSee('İçeridekiler')
            ->assertSee('Giriş')->assertSee('<html lang="tr">', false);

        $this->actingAs($this->user)->post('/dil/de')->assertNotFound();   // desteklenmeyen dil
    }

    public function test_language_can_be_chosen_on_login_page_and_messages_follow_it(): void
    {
        $this->get('/giris')->assertSee('User name');
        $this->from('/giris')->post('/dil/tr')->assertRedirect('/giris');
        $this->get('/giris')->assertSee('Kullanıcı adı');
        $this->post('/giris', ['login' => 'MU', 'password' => 'yanlis'])->assertSessionHasErrors(['login' => 'Kullanıcı adı ya da şifre hatalı.']);
    }

    public function test_language_files_have_the_same_keys(): void
    {
        $flatten = function (array $a, string $p = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                $out = array_merge($out, is_array($v) ? $flatten($v, "{$p}{$k}.") : ["{$p}{$k}"]);
            }

            return $out;
        };
        $en = $flatten(require lang_path('en/pms.php'));
        $tr = $flatten(require lang_path('tr/pms.php'));
        $this->assertSame([], array_values(array_diff($en, $tr)), 'Türkçede eksik anahtar');
        $this->assertSame([], array_values(array_diff($tr, $en)), 'İngilizcede eksik anahtar');
    }
}
