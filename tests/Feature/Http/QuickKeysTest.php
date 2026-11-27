<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class QuickKeysTest extends DomainTestCase
{
    private function user(string $role): User
    {
        $user = User::create(['username' => 'U'.$role, 'name' => $role, 'email' => strtolower($role).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', $role)->value('id')]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
    }

    public function test_quick_keys_menu_and_global_hotkeys(): void
    {
        $html = $this->actingAs($this->user('admin'))->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Quick Keys', $html);
        foreach (['Ctrl+A', 'Ctrl+B', 'Ctrl+I', 'Ctrl+L', 'Ctrl+N', 'Ctrl+U', 'Ctrl+Shift+R', 'F1'] as $key) {
            $this->assertStringContainsString($key, $html);
        }
        $this->assertStringContainsString('href="'.route('reservations.navigator', ['f' => 'gelecek']).'" data-hotkey="ctrl+a" data-hotkey-global', $html);
        $this->assertStringContainsString('data-hotkey="ctrl+n alt+shift+n" data-hotkey-global', $html);
        $this->assertStringContainsString('href="'.route('shortcuts').'" data-hotkey="f1" data-hotkey-global', $html);
        $this->assertStringContainsString('href="'.route('front-office.house-status').'" data-hotkey="ctrl+h" data-hotkey-global', $html);
        $this->assertStringContainsString('href="'.route('reports.index').'" data-hotkey="ctrl+shift+r alt+shift+r" data-hotkey-global', $html);
    }

    public function test_hotkeys_follow_permissions(): void
    {
        $html = $this->actingAs($this->user('HKS'))->get('/')->assertOk()->getContent();   // kat hizmetleri: rezervasyon açamaz
        $this->assertStringNotContainsString('data-hotkey="ctrl+n alt+shift+n"', $html);
        $this->assertStringNotContainsString('data-hotkey="ctrl+b"', $html);
        $this->assertStringContainsString('data-hotkey="ctrl+i" data-hotkey-global', $html);
    }

    public function test_shortcuts_screen(): void
    {
        $this->actingAs($this->user('FOA'))->get('/kisayollar')->assertOk()
            ->assertSee('Shortcuts')->assertSee('Reservation Navigator')->assertSee('Alt+1 … Alt+8')->assertSee('Alt+Shift+N')->assertSee('Not available yet');
    }
}
