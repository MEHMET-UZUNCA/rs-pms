<?php

namespace Tests\Feature\Http;

use App\Models\User;
use App\Support\LoginAppearance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Domain\DomainTestCase;

class LoginScreenTest extends DomainTestCase
{
    public function test_background_title_and_overlay_are_set_from_setup_and_shown_on_login(): void
    {
        $this->withoutVite();
        Storage::fake(LoginAppearance::DISK);
        $this->get('/giris')->assertOk()->assertSee('data-login-screen', false)->assertDontSee('data-login-background', false);
        $this->get('/giris/arka-plan')->assertNotFound();
        // Enter girişi yapmalı: dil düğmeleri giriş formunun değil ayrı formun düğmesi (yoksa Enter dili değiştirir)
        $html = $this->get('/giris')->getContent();
        $this->assertSame(substr_count($html, 'formaction="'.route('locale', 'en')), substr_count($html, 'form="lang-form" formaction="'.route('locale', 'en')));

        $admin = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $admin->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
        $this->actingAs($admin)->get('/ayarlar/giris-ekrani')->assertOk()->assertSee('data-login-screen-form', false);
        $this->actingAs($admin)->post('/ayarlar/giris-ekrani', ['overlay' => 30, 'title' => 'Rocks Hotel & Casino', 'subtitle' => 'Girne',
            'image' => UploadedFile::fake()->image('deniz.jpg', 1600, 900)])->assertSessionHas('status');
        $this->actingAs($admin)->post('/ayarlar/giris-ekrani', ['overlay' => 30, 'image' => UploadedFile::fake()->image('kucuk.jpg', 300, 200)])->assertSessionHasErrors('image');

        auth()->logout();
        $page = $this->get('/giris')->assertOk();
        $page->assertSee('data-login-background', false)->assertSee('Rocks Hotel &amp; Casino', false)->assertSee('Girne');
        $this->get('/giris/arka-plan')->assertOk();

        // Kaldırılınca varsayılan arka plana döner
        $this->actingAs($admin)->post('/ayarlar/giris-ekrani', ['overlay' => 30, 'remove_image' => 1]);
        auth()->logout();
        $this->get('/giris')->assertDontSee('data-login-background', false);
        $this->assertSame([], Storage::disk(LoginAppearance::DISK)->files(LoginAppearance::DIR));
    }
}
