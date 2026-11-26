<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class BillingScreenTest extends DomainTestCase
{
    private User $user;

    private Reservation $res;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'KASA', 'name' => 'Kasa', 'email' => 'kasa@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
        $this->res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Yılmaz'))->firstOrFail();   // 1102, içeride, 13.450
    }

    private function balance(int $window = 1): float
    {
        return (float) DB::table('pms_folios')->where('reservation_id', $this->res->id)->where('window', $window)->value('balance');
    }

    public function test_billing_shows_windows_postings_and_balance(): void
    {
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}")->assertOk()
            ->assertSee('Billing — Yılmaz, Ahmet')->assertSee('Window 1')->assertSee('Window 8')->assertSee('data-hotkey="alt+8"', false)
            ->assertSee('Cafe Rocks (örnek)')->assertSee('13.450,00')->assertSee('Check Out');
        $this->actingAs($this->user)->get('/rezervasyon')->assertSee(route('billing.reservation', $this->res), false);
    }

    public function test_manual_posting_with_quantity_and_tax_then_payment(): void
    {
        $this->actingAs($this->user)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'window' => 1, 'code' => '510', 'amount' => '116', 'quantity' => '2', 'description' => 'Ütü'])
            ->assertSessionHas('status');
        $p = DB::table('pms_postings')->where('description', 'Ütü')->first();
        $this->assertSame([232.0, 32.0, 2.0], [(float) $p->amount, (float) $p->tax_amount, (float) $p->quantity]);   // 510 Laundry %16
        $this->assertSame((int) $this->user->id, (int) $p->posted_by);
        $this->assertSame(13450.0 + 232, $this->balance());

        $visa = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '910')->value('id');
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $visa, 'amount' => '682'])->assertSessionHas('status');
        $this->assertSame(13000.0, $this->balance());
    }

    public function test_posting_rules(): void
    {
        $this->actingAs($this->user)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'code' => '9999', 'amount' => '10'])->assertSessionHasErrors('code');
        $this->actingAs($this->user)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'code' => '900', 'amount' => '10'])->assertSessionHasErrors('code');   // ödeme kodu
        DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '510')->update(['manual_post_allowed' => false]);
        $this->actingAs($this->user)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'code' => '510', 'amount' => '10'])
            ->assertSessionHasErrors(['code' => 'Department code 510 is not allowed for manual posting.']);
    }

    public function test_correction_and_transfer_to_other_window(): void
    {
        $cafe = DB::table('pms_postings')->where('folio_id', DB::table('pms_folios')->where('reservation_id', $this->res->id)->value('id'))
            ->where('description', 'Cafe Rocks (örnek)')->first();

        $this->actingAs($this->user)->post("/folyo/kayit/{$cafe->id}/aktar", ['window' => 2])->assertSessionHas('status', 'Posting transferred to window 2.');
        $this->assertSame(13450.0 - 850, $this->balance(1));
        $this->assertSame(850.0, $this->balance(2));
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}?pencere=2")->assertSee('Cafe Rocks (örnek)')->assertSee('850,00');

        $moved = DB::table('pms_postings')->where('transfer_from_id', $cafe->id)->value('id');
        $this->actingAs($this->user)->post("/folyo/kayit/{$moved}/duzelt", ['note' => 'Yanlış oda'])->assertSessionHas('status');
        $this->assertSame(0.0, $this->balance(2));
        $this->actingAs($this->user)->post("/folyo/kayit/{$moved}/duzelt")->assertSessionHas('error');   // ikinci kez olmaz

        // Düzeltmeler varsayılan olarak gizli, istenirse görünür
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}?pencere=2")->assertSee('No postings in this window.');
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}?pencere=2&duzeltmeler=1")->assertSee('(reversed)');
    }

    public function test_check_out_requires_zero_balance(): void
    {
        $this->actingAs($this->user)->post("/folyo/rezervasyon/{$this->res->id}/cikis")->assertSessionHas('error');
        $cash = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '900')->value('id');
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'payment_code_id' => $cash, 'amount' => '13450']);
        $this->actingAs($this->user)->post("/folyo/rezervasyon/{$this->res->id}/cikis")->assertRedirect(route('reservations.navigator', ['f' => 'cikmis']));
        $this->assertSame('checked_out', $this->res->fresh()->status);
        $this->assertSame('dirty', DB::table('pms_rooms')->where('number', '1102')->value('hk_status'));
    }

    public function test_financial_account_billing_and_permissions(): void
    {
        $fa = DB::table('pms_folios')->where('folio_number', 'FA-DEMO-1')->value('id');
        $this->actingAs($this->user)->get("/folyo/hesap/{$fa}")->assertOk()->assertSee('FA-DEMO-1')->assertSee('Otopark (örnek)');
        $this->actingAs($this->user)->post('/folyo/kayit', ['folio_id' => $fa, 'code' => '630', 'amount' => '150'])->assertSessionHas('status');
        $this->assertSame(450.0, (float) DB::table('pms_folios')->where('id', $fa)->value('balance'));

        $viewer = User::create(['username' => 'GOR', 'name' => 'G', 'email' => 'g@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        $role = DB::table('pms_roles')->insertGetId(['code' => 'billing_view', 'name' => 'Folyo görüntüleme']);
        DB::table('pms_role_permissions')->insert(['role_id' => $role, 'permission_id' => DB::table('pms_permissions')->where('code', 'cashiering.billing')->value('id')]);
        DB::table('pms_user_properties')->insert(['user_id' => $viewer->id, 'property_id' => $this->property, 'role_id' => $role]);
        $this->actingAs($viewer)->get("/folyo/rezervasyon/{$this->res->id}")->assertOk()->assertDontSee('data-hotkey="alt+p"', false);
        $this->actingAs($viewer)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'code' => '510', 'amount' => '10'])->assertForbidden();
    }
}
