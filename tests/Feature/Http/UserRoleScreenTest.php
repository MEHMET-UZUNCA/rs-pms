<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Domain\DomainTestCase;

class UserRoleScreenTest extends DomainTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->admin = $this->makeUser('ADMIN', 'admin');
    }

    private function role(string $code): int
    {
        return (int) DB::table('pms_roles')->where('code', $code)->value('id');
    }

    private function makeUser(string $username, string $role): User
    {
        $user = User::create(['username' => $username, 'name' => $username, 'email' => strtolower($username).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => $this->role($role)]);

        return $user;
    }

    public function test_user_list_and_new_user_gets_temporary_password(): void
    {
        $this->makeUser('AYSE', 'FOA');
        $this->actingAs($this->admin)->get('/ayarlar/kullanicilar')->assertOk()
            ->assertSee('AYSE')->assertSee('Front Office Agent')->assertSee('data-hotkey="alt+p"', false);

        $response = $this->actingAs($this->admin)->post('/ayarlar/kullanicilar', [
            'username' => 'mehmet.k', 'name' => 'Mehmet Kaya', 'initials' => 'MK', 'title' => 'Receptionist', 'email' => '',
            'locale' => 'tr', 'role_id' => $this->role('FOA'), 'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('temp_password');

        $temp = session('temp_password');
        $user = User::where('username', 'MEHMET.K')->firstOrFail();
        $this->assertSame('MEHMET.K', $temp['username']);
        $this->assertTrue(Hash::check($temp['password'], $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame('mehmet.k@rocks.local', $user->email);
        $this->assertSame($this->role('FOA'), (int) DB::table('pms_user_properties')->where('user_id', $user->id)->value('role_id'));

        $this->actingAs($this->admin)->get('/ayarlar/kullanicilar')->assertSee('MEHMET.K');
    }

    public function test_edit_user_role_and_deactivate(): void
    {
        $user = $this->makeUser('AYSE', 'FOA');
        $this->actingAs($this->admin)->get("/ayarlar/kullanicilar/{$user->id}/duzenle")->assertOk()->assertSee('value="AYSE"', false);

        $this->actingAs($this->admin)->put("/ayarlar/kullanicilar/{$user->id}", [
            'username' => 'AYSE', 'name' => 'Ayşe Demir', 'locale' => 'en', 'role_id' => $this->role('HKS'), 'is_active' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertSame(1, DB::table('pms_user_properties')->where('user_id', $user->id)->count());
        $this->assertSame($this->role('HKS'), (int) DB::table('pms_user_properties')->where('user_id', $user->id)->value('role_id'));
        $this->actingAs($this->admin)->get('/ayarlar/kullanicilar')->assertDontSee('Ayşe Demir');
        $this->actingAs($this->admin)->get('/ayarlar/kullanicilar?pasif=1')->assertSee('Ayşe Demir');
    }

    public function test_reset_password(): void
    {
        $user = $this->makeUser('AYSE', 'FOA');
        $this->actingAs($this->admin)->post("/ayarlar/kullanicilar/{$user->id}/sifre")->assertRedirect()->assertSessionHas('temp_password');
        $user->refresh();
        $this->assertTrue(Hash::check(session('temp_password')['password'], $user->password));
        $this->assertFalse(Hash::check('gizli-sifre', $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_validation_and_self_protection(): void
    {
        $this->makeUser('AYSE', 'FOA');
        $this->actingAs($this->admin)->post('/ayarlar/kullanicilar', ['username' => 'ayse', 'name' => 'x', 'locale' => 'en', 'role_id' => $this->role('FOA')])
            ->assertSessionHasErrors('username');
        $this->actingAs($this->admin)->post('/ayarlar/kullanicilar', ['username' => 'a b', 'name' => 'x', 'locale' => 'en', 'role_id' => $this->role('FOA')])
            ->assertSessionHasErrors('username');

        $this->actingAs($this->admin)->put("/ayarlar/kullanicilar/{$this->admin->id}", ['username' => 'ADMIN', 'name' => 'Admin', 'locale' => 'en', 'role_id' => $this->role('admin'), 'is_active' => '0'])
            ->assertSessionHasErrors();
        $this->actingAs($this->admin)->put("/ayarlar/kullanicilar/{$this->admin->id}", ['username' => 'ADMIN', 'name' => 'Admin', 'locale' => 'en', 'role_id' => $this->role('FOA'), 'is_active' => '1'])
            ->assertSessionHasErrors();
        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertSame($this->role('admin'), (int) DB::table('pms_user_properties')->where('user_id', $this->admin->id)->value('role_id'));
    }

    public function test_role_permissions_are_edited_and_take_effect(): void
    {
        $user = $this->makeUser('KAT', 'HKS');
        $this->actingAs($user)->get('/kat-hizmetleri')->assertOk();
        $this->actingAs($user)->get('/rezervasyon/yeni')->assertForbidden();

        $html = $this->actingAs($this->admin)->get('/ayarlar/roller/'.$this->role('HKS').'/duzenle')->assertOk()->getContent();
        $this->assertStringContainsString('Housekeeping', $html);
        $this->assertStringContainsString('Change room status', $html);

        $ids = DB::table('pms_permissions')->whereIn('code', ['reservations.view', 'reservations.edit', 'housekeeping.view'])->pluck('id')->all();
        $this->actingAs($this->admin)->put('/ayarlar/roller/'.$this->role('HKS'), ['code' => 'HKS', 'name' => 'Housekeeping Supervisor', 'permissions' => $ids])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, DB::table('pms_role_permissions')->where('role_id', $this->role('HKS'))->count());
        $this->actingAs($user)->get('/rezervasyon/yeni')->assertOk();
    }

    public function test_new_copy_and_delete_role(): void
    {
        $this->actingAs($this->admin)->get('/ayarlar/roller')->assertOk()->assertSee('FOM')->assertSee('Front Office Manager');
        $this->actingAs($this->admin)->get('/ayarlar/roller/'.$this->role('FOA').'/kopyala')->assertOk()->assertSee('checked', false);

        $ids = DB::table('pms_permissions')->where('code', 'reservations.view')->pluck('id')->all();
        $this->actingAs($this->admin)->post('/ayarlar/roller', ['code' => 'night2', 'name' => 'Night Clerk', 'permissions' => $ids])->assertRedirect();
        $id = $this->role('NIGHT2');
        $this->assertGreaterThan(0, $id);

        $this->makeUser('GECE', 'NIGHT2');
        $this->actingAs($this->admin)->delete("/ayarlar/roller/{$id}")->assertSessionHas('error');                 // kullanıcıya verilmiş
        $this->actingAs($this->admin)->delete('/ayarlar/roller/'.$this->role('admin'))->assertSessionHas('error');   // sistem yöneticisi
        $this->actingAs($this->admin)->delete('/ayarlar/roller/'.$this->role('IFC'))->assertRedirect('/ayarlar/roller');
        $this->assertSame(0, $this->role('IFC'));
    }

    public function test_own_role_keeps_user_management_permission(): void
    {
        $ids = DB::table('pms_permissions')->where('code', '!=', 'setup.users')->pluck('id')->all();
        $this->actingAs($this->admin)->put('/ayarlar/roller/'.$this->role('admin'), ['code' => 'ADMIN', 'name' => 'Admin', 'permissions' => $ids])
            ->assertSessionHasErrors('permissions');
    }

    public function test_screens_need_permission(): void
    {
        $clerk = $this->makeUser('AYSE', 'FOA');
        $this->actingAs($clerk)->get('/ayarlar/kullanicilar')->assertForbidden();
        $this->actingAs($clerk)->get('/ayarlar/roller')->assertForbidden();
        $this->actingAs($clerk)->post("/ayarlar/kullanicilar/{$this->admin->id}/sifre")->assertForbidden();
    }
}
