<?php

namespace Tests\Feature\Http;

use App\Domain\Pos\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class OutletScreenTest extends DomainTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = $this->user('ADMIN', 'admin');
    }

    private function user(string $username, string $role): User
    {
        $user = User::create(['username' => $username, 'name' => $username, 'email' => strtolower($username).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        $roleId = DB::table('pms_roles')->where('code', $role)->value('id') ?? DB::table('pms_roles')->insertGetId(['code' => $role, 'name' => $role]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => $roleId]);

        return $user;
    }

    private function outlet(string $code): Outlet
    {
        return Outlet::where('property_id', $this->property)->where('code', $code)->firstOrFail();
    }

    public function test_guests_are_sent_to_login_and_can_sign_in_with_username(): void
    {
        $this->get('/ayarlar/outletler')->assertRedirect('/giris');
        $this->get('/giris')->assertOk()->assertSee('User name');   // varsayılan dil İngilizce

        $this->post('/giris', ['login' => 'admin', 'password' => 'yanlis'])->assertSessionHasErrors('login');
        $this->post('/giris', ['login' => 'admin', 'password' => 'gizli-sifre'])->assertRedirect('/ayarlar/outletler');   // girişten önce istenen ekrana döner
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_sign_in_and_user_without_permission_is_forbidden(): void
    {
        $this->admin->update(['is_active' => false]);
        $this->post('/giris', ['login' => 'ADMIN', 'password' => 'gizli-sifre'])->assertSessionHasErrors('login');

        $clerk = $this->user('RESEPSIYON', 'reception');
        $this->actingAs($clerk)->get('/ayarlar/outletler')->assertForbidden();
        $this->actingAs($clerk)->get('/')->assertOk()->assertDontSee('Ayarlar → Outlet');
    }

    public function test_list_shows_rocks_outlets_in_order_with_business_date_and_search(): void
    {
        $this->actingAs($this->admin)->get('/ayarlar/outletler')->assertOk()
            ->assertSeeInOrder(['La Habenera', "L'Hirondelle", 'Cafe Rocks', 'Pool Bar', 'Minibar', 'Room Service', 'Banket', 'Spa Center', 'Laundry', 'Casino', 'Kuaför', 'Catch Seafood', 'Onyx', 'Byblos', 'Rocks Pattiserie', 'Sıralı Et', 'Lyra', 'Dragon Rest'], false)
            ->assertSee('01.12.2026')   // iş günü durum çubuğunda
            ->assertSee('data-hotkey="alt+n insert"', false);

        $this->actingAs($this->admin)->get('/ayarlar/outletler?q=byb')->assertOk()->assertSee('Byblos')->assertDontSee('Cafe Rocks');

        $this->assertSame(9, $this->outlet('BYBL')->transactionCodes()->count());    // 700–702, 710–712, 720–722
        $this->assertSame('700', $this->outlet('BYBL')->defaultTransactionCode->code);
        $this->assertSame(14, $this->outlet('BYBL')->simphony_rvc_number);                // EMC → Revenue Centers "#"
        $this->assertSame(['510', '511', '512'], $this->outlet('LAUN')->transactionCodes()->orderBy('code')->pluck('code')->all());
        $this->assertNull($this->outlet('DRAG')->simphony_rvc_number);
        $this->assertSame('manual', $this->outlet('DRAG')->source_system);
    }

    public function test_new_outlet_is_saved_with_codes_and_default_code(): void
    {
        DB::table('pms_transaction_codes')->where('property_id', $this->property)->whereIn('code', ['470', '471', '472'])->update(['outlet_id' => null]);
        $ids = DB::table('pms_transaction_codes')->where('property_id', $this->property)->whereIn('code', ['470', '471', '472'])->pluck('id', 'code');

        $this->actingAs($this->admin)->get('/ayarlar/outletler/yeni')->assertOk()->assertSee('472')->assertSee('REBATE Tobacco');

        $this->actingAs($this->admin)->post('/ayarlar/outletler', [
            'code' => 'tob', 'name' => 'Tütün satış', 'type' => 'shop', 'source_system' => 'simphony', 'simphony_rvc_number' => 21,
            'is_active' => '1', 'codes' => [$ids['470'], $ids['471'], $ids['472']], 'default_transaction_code_id' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $outlet = $this->outlet('TOB');
        $this->assertSame('Tütün satış', $outlet->name);
        $this->assertSame(21, $outlet->simphony_rvc_number);
        $this->assertSame((int) $ids['470'], (int) $outlet->default_transaction_code_id);   // otomatik: en küçük numara
        $this->assertSame(3, $outlet->transactionCodes()->count());
        $this->assertSame(19, $outlet->sort);
    }

    public function test_validation_rejects_duplicate_code_rvc_and_codes_of_other_outlets(): void
    {
        $cafeCode = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '320')->value('id');
        $cash = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '900')->value('id');
        $base = ['name' => 'X', 'type' => 'bar', 'source_system' => 'simphony', 'is_active' => '1'];

        $this->actingAs($this->admin)->post('/ayarlar/outletler', $base + ['code' => 'pool'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/ayarlar/outletler', $base + ['code' => 'NEW', 'simphony_rvc_number' => 1])->assertSessionHasErrors('simphony_rvc_number');
        $this->actingAs($this->admin)->post('/ayarlar/outletler', $base + ['code' => 'NEW', 'codes' => [$cafeCode]])
            ->assertSessionHasErrors(['codes' => '320 kodu "Cafe Rocks" outlet\'ine bağlı. Önce oradan çıkarın.']);
        $this->actingAs($this->admin)->post('/ayarlar/outletler', $base + ['code' => 'NEW', 'codes' => [$cash]])->assertSessionHasErrors('codes');
        $this->assertNull(Outlet::where('code', 'NEW')->first());
    }

    public function test_edit_moves_codes_out_and_deactivates(): void
    {
        $cafe = $this->outlet('CAFE');
        $keep = DB::table('pms_transaction_codes')->where('outlet_id', $cafe->id)->whereIn('code', ['320', '321'])->pluck('id')->all();

        $this->actingAs($this->admin)->get("/ayarlar/outletler/{$cafe->id}/duzenle")->assertOk()->assertSee('Cafe Rocks');
        $this->actingAs($this->admin)->put("/ayarlar/outletler/{$cafe->id}", [
            'code' => 'CAFE', 'name' => 'Cafe Rocks', 'type' => 'restaurant', 'source_system' => 'simphony', 'is_active' => '0',
            'codes' => $keep, 'default_transaction_code_id' => $keep[1],
        ])->assertSessionHasNoErrors();

        $cafe->refresh();
        $this->assertFalse($cafe->is_active);
        $this->assertSame(2, $cafe->transactionCodes()->count());
        $this->assertNull(DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '322')->value('outlet_id'));
        $this->actingAs($this->admin)->get('/ayarlar/outletler')->assertDontSee('<td>Cafe Rocks</td>', false);
        $this->actingAs($this->admin)->get('/ayarlar/outletler?pasif=1')->assertSee('Cafe Rocks');
    }

    public function test_used_outlet_cannot_be_deleted_unused_one_can(): void
    {
        $pool = $this->outlet('POOL');
        DB::table('pms_pos_revenue_mappings')->insert(['property_id' => $this->property, 'outlet_id' => $pool->id, 'source_type' => 'major_group',
            'source_code' => '1', 'transaction_code_id' => $pool->default_transaction_code_id]);

        $this->actingAs($this->admin)->delete("/ayarlar/outletler/{$pool->id}")->assertSessionHas('error');
        $this->assertNotNull(Outlet::find($pool->id));

        $drag = $this->outlet('DRAG');
        $this->actingAs($this->admin)->delete("/ayarlar/outletler/{$drag->id}")->assertSessionHas('status');
        $this->assertNull(Outlet::find($drag->id));
        $this->assertNull(DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '410')->value('outlet_id'));
    }

    public function test_up_down_reorders_and_copy_prefills_form(): void
    {
        $pool = $this->outlet('POOL');
        $this->actingAs($this->admin)->post("/ayarlar/outletler/{$pool->id}/tasi/up")->assertRedirect();
        $this->assertSame(3, $pool->fresh()->sort);
        $this->assertSame(4, $this->outlet('CAFE')->sort);

        $this->actingAs($this->admin)->get("/ayarlar/outletler/{$pool->id}/kopyala")->assertOk()->assertSee('Pool Bar (copy)');
    }

    public function test_create_user_command_links_user_to_property(): void
    {
        $this->artisan('pms:create-user', ['username' => 'mu', 'name' => 'M.U', 'email' => 'MU@rocks.test', '--password' => 'uzun-sifre-1'])->assertSuccessful();
        $user = User::where('username', 'MU')->firstOrFail();
        $this->assertSame('mu@rocks.test', $user->email);
        $this->post('/giris', ['login' => 'mu', 'password' => 'uzun-sifre-1'])->assertRedirect('/');
        $this->actingAs($user)->get('/ayarlar/outletler')->assertOk();
    }
}
