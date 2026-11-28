<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class SetupCatalogTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    public function test_screens_open_and_tree_links_are_active(): void
    {
        foreach (['/ayarlar/fiyat-kategorileri' => 'categories', '/ayarlar/sezonlar' => 'seasons', '/ayarlar/paketler' => 'packages',
            '/ayarlar/departman-gruplari' => 'groups', '/ayarlar/vergiler' => 'taxes', '/ayarlar/odeme-turleri' => 'payment-types'] as $url => $key) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->assertSee('data-catalog="'.$key.'"', false)->getContent();
            $this->assertStringNotContainsString('<span class="is-soon">', $html);   // ağaçta soluk ekran kalmadı
        }
    }

    public function test_rate_categories_and_seasons_with_non_overlapping_dates(): void
    {
        $this->actingAs($this->user)->post('/ayarlar/fiyat-kategorileri', ['code' => 'y2028', 'name' => '2028 Fiyatları'])->assertSessionHas('status');
        $this->assertTrue(DB::table('pms_rate_categories')->where('code', 'Y2028')->exists());
        $used = DB::table('pms_rate_codes')->whereNotNull('rate_category_id')->value('rate_category_id');
        $this->actingAs($this->user)->delete("/ayarlar/fiyat-kategorileri/{$used}")->assertSessionHas('error');

        $this->actingAs($this->user)->post('/ayarlar/sezonlar', ['code' => 'high', 'name' => 'Yüksek sezon', 'color' => '#ff0000'])->assertSessionHas('status');
        $this->actingAs($this->user)->post('/ayarlar/sezonlar', ['code' => 'low', 'name' => 'Düşük sezon'])->assertSessionHas('status');
        $high = DB::table('pms_seasons')->where('code', 'HIGH')->value('id');
        $low = DB::table('pms_seasons')->where('code', 'LOW')->value('id');
        $this->actingAs($this->user)->post("/ayarlar/sezonlar/{$high}/tarihler", ['date_from' => '01.06.2027', 'date_to' => '31.08.2027'])->assertSessionHas('status');
        $this->actingAs($this->user)->post("/ayarlar/sezonlar/{$low}/tarihler", ['date_from' => '15.08.2027', 'date_to' => '30.09.2027'])->assertSessionHas('error');   // çakışma
        $this->actingAs($this->user)->post("/ayarlar/sezonlar/{$low}/tarihler", ['date_from' => '01.09.2027', 'date_to' => '30.09.2027'])->assertSessionHas('status');
        $this->assertSame(2, DB::table('pms_season_dates')->count());
        $this->actingAs($this->user)->delete("/ayarlar/sezonlar/{$low}")->assertSessionHas('status');
        $this->assertSame(1, DB::table('pms_season_dates')->count());
    }

    public function test_packages_and_elements(): void
    {
        $this->actingAs($this->user)->post('/ayarlar/paketler', ['code' => 'ubb', 'name' => 'Ultra Breakfast', 'type' => 'board', 'posting_rhythm' => 'every_night',
            'is_included_in_rate' => 1, 'includes_breakfast' => 1])->assertRedirect();
        $pkg = DB::table('pms_packages')->where('code', 'UBB')->first();
        $this->assertSame([1, 0], [(int) $pkg->includes_breakfast, (int) $pkg->includes_dinner]);
        $code = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '110')->value('id');
        $this->actingAs($this->user)->post("/ayarlar/paketler/{$pkg->id}/elemanlar", ['transaction_code_id' => $code, 'calc' => 'per_person', 'amount_adult' => '17,50', 'amount_child' => '8'])
            ->assertSessionHas('status');
        $this->assertEquals(17.5, (float) DB::table('pms_package_elements')->where('package_id', $pkg->id)->value('amount_adult'));
        $this->actingAs($this->user)->get("/ayarlar/paketler?paket={$pkg->id}")->assertSee('data-package-elements', false)->assertSee('110 ');
        $payment = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('type', 'payment')->value('id');
        $this->actingAs($this->user)->post("/ayarlar/paketler/{$pkg->id}/elemanlar", ['transaction_code_id' => $payment, 'calc' => 'per_room', 'amount_adult' => 1, 'amount_child' => 0])
            ->assertSessionHasErrors('transaction_code_id');   // ödeme kodu paket elemanı olamaz
        $used = DB::table('pms_rate_codes')->whereNotNull('package_id')->value('package_id');
        if ($used) {
            $this->actingAs($this->user)->delete("/ayarlar/paketler/{$used}")->assertSessionHas('error');
        }
        $this->actingAs($this->user)->delete("/ayarlar/paketler/{$pkg->id}")->assertSessionHas('status');
        $this->assertSame(0, DB::table('pms_package_elements')->where('package_id', $pkg->id)->count());
    }

    public function test_groups_taxes_and_payment_types(): void
    {
        $main = DB::table('pms_transaction_code_groups')->where('code', '20')->value('id');
        $this->actingAs($this->user)->post('/ayarlar/departman-gruplari', ['code' => '21', 'name' => 'Restoran yiyecek', 'type' => 'revenue', 'parent_id' => $main])->assertSessionHas('status');
        $this->assertSame((int) $main, (int) DB::table('pms_transaction_code_groups')->where('code', '21')->value('parent_id'));
        $this->actingAs($this->user)->delete("/ayarlar/departman-gruplari/{$main}")->assertSessionHas('error');   // alt grubu var

        // Kayıtlarda kullanılmış vergi: oran değişmez, bitiş tarihi verilebilir
        $tax = DB::table('pms_taxes')->whereIn('id', DB::table('pms_posting_taxes')->select('tax_id'))->first() ?? DB::table('pms_taxes')->where('code', 'VAT5')->first();
        DB::table('pms_posting_taxes')->where('tax_id', $tax->id)->exists() || $this->markTestIncomplete('demo verisinde vergili kayıt yok');
        $base = ['code' => $tax->code, 'name' => $tax->name, 'calc_type' => 'percent', 'is_inclusive' => 1, 'valid_from' => '01.01.2001'];
        $this->actingAs($this->user)->put("/ayarlar/vergiler/{$tax->id}", $base + ['rate' => '7'])->assertSessionHas('error');
        $this->actingAs($this->user)->put("/ayarlar/vergiler/{$tax->id}", $base + ['rate' => (string) (float) $tax->rate, 'valid_to' => '31.12.2027'])->assertSessionHas('status');
        $this->assertSame('2027-12-31', substr((string) DB::table('pms_taxes')->where('id', $tax->id)->value('valid_to'), 0, 10));
        $this->actingAs($this->user)->post('/ayarlar/vergiler', ['code' => 'vat6', 'name' => 'KDV %6', 'calc_type' => 'percent', 'rate' => '6', 'is_inclusive' => 1, 'valid_from' => '01.01.2028'])
            ->assertSessionHas('status');
        $this->actingAs($this->user)->delete("/ayarlar/vergiler/{$tax->id}")->assertSessionHas('error');

        $pay = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('type', 'payment')->first();
        $this->actingAs($this->user)->put("/ayarlar/odeme-turleri/{$pay->id}", ['payment_kind' => 'card', 'cc_commission_pct' => '2,5', 'is_active' => 1])->assertSessionHas('status');
        $this->assertSame(['card', 2.5], [DB::table('pms_transaction_codes')->where('id', $pay->id)->value('payment_kind'), (float) DB::table('pms_transaction_codes')->where('id', $pay->id)->value('cc_commission_pct')]);
    }
}
