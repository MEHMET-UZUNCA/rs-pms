<?php

namespace Tests\Feature\Http;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\InvoiceService;
use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class CashierAndInvoiceTest extends DomainTestCase
{
    private User $user;

    private Reservation $res;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'KASA', 'name' => 'Kasa Bir', 'email' => 'kasa@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Yılmaz'))->firstOrFail();   // 1102, içeride, 13.450
        $bd = DB::table('pms_properties')->where('id', $this->property)->value('current_business_date');
        DB::table('pms_exchange_rates')->insert(['property_id' => $this->property, 'currency_code' => 'EUR', 'business_date' => $bd, 'rate_type' => 'day',
            'buy_rate' => 40, 'sell_rate' => 41, 'posting_rate' => 41, 'cash_rate' => 39, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tc(string $code): int
    {
        return (int) DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function balance(): float
    {
        return (float) DB::table('pms_folios')->where('reservation_id', $this->res->id)->where('window', 1)->value('balance');
    }

    public function test_foreign_currency_payment_uses_cash_or_posting_rate(): void
    {
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}")->assertOk()->assertSee('<option value="EUR"', false);

        // Nakit EUR: Cash Rate 39
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('900'), 'amount' => '100', 'currency_code' => 'EUR'])
            ->assertSessionHas('status');
        $p = DB::table('pms_postings')->where('foreign_currency', 'EUR')->sole();
        $this->assertSame([-3900.0, -100.0, 39.0], [(float) $p->amount, (float) $p->foreign_amount, (float) $p->exchange_rate]);
        $this->assertSame(13450.0 - 3900, $this->balance());

        // Kartla EUR: Posting Rate 41
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('910'), 'amount' => '10', 'currency_code' => 'EUR'])
            ->assertSessionHas('status');
        $this->assertSame(-410.0, (float) DB::table('pms_postings')->where('foreign_currency', 'EUR')->orderByDesc('id')->value('amount'));

        // Kuru olmayan döviz
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('900'), 'amount' => '10', 'currency_code' => 'GBP'])
            ->assertSessionHasErrors('currency_code');
    }

    public function test_shift_opens_with_first_posting_and_closes_with_counted_cash(): void
    {
        $this->actingAs($this->user)->get('/kasa')->assertOk()->assertSee('You have no open shift.');
        $this->actingAs($this->user)->post('/folyo/kayit', ['reservation_id' => $this->res->id, 'window' => 1, 'code' => '510', 'amount' => '50'])->assertSessionHas('status');
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('900'), 'amount' => '1000'])->assertSessionHas('status');
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('900'), 'amount' => '50', 'currency_code' => 'EUR']);
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('910'), 'amount' => '500']);

        $shift = DB::table('pms_cashier_shifts')->sole();
        $this->assertSame(4, DB::table('pms_postings')->where('cashier_shift_id', $shift->id)->count());
        $cashier = DB::table('pms_cashiers')->where('id', $shift->cashier_id)->first();
        $this->assertSame([(int) $this->user->id, 'Kasa Bir'], [(int) $cashier->user_id, $cashier->name]);

        $html = $this->actingAs($this->user)->get('/kasa')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-system="900-TRY">1\.000,00</', $html);
        $this->assertMatchesRegularExpression('/data-system="900-EUR">50,00</', $html);
        $this->assertMatchesRegularExpression('/data-system="910-TRY">500,00</', $html);

        $this->actingAs($this->user)->post('/kasa/kapat', ['counted' => [$this->tc('900').'|TRY' => '990,00', $this->tc('900').'|EUR' => '50'], 'notes' => 'Eksik 10 TL'])
            ->assertRedirect("/kasa/vardiya/{$shift->id}");
        $this->assertSame('closed', DB::table('pms_cashier_shifts')->where('id', $shift->id)->value('status'));
        $totals = DB::table('pms_cashier_shift_totals')->where('shift_id', $shift->id)->get()->keyBy(fn ($t) => $t->transaction_code_id.'|'.$t->currency_code);
        $this->assertSame([1000.0, 990.0, -10.0], [(float) $totals[$this->tc('900').'|TRY']->system_amount, (float) $totals[$this->tc('900').'|TRY']->counted_amount, (float) $totals[$this->tc('900').'|TRY']->difference]);
        $this->assertSame(0.0, (float) $totals[$this->tc('910').'|TRY']->difference);   // boş: sistem tutarı
        $this->actingAs($this->user)->get("/kasa/vardiya/{$shift->id}")->assertOk()->assertSee('Cashier report')->assertSee('Eksik 10 TL')->assertSee('-10,00');

        // Sonraki ödeme yeni vardiya açar
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('900'), 'amount' => '5']);
        $this->assertSame(2, DB::table('pms_cashier_shifts')->count());

        // Başka kasiyerin raporu: yetkisi olmayan göremez
        $other = User::create(['username' => 'DIGER', 'name' => 'Diğer', 'email' => 'd@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $other->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($other)->get("/kasa/vardiya/{$shift->id}")->assertForbidden();
    }

    public function test_folio_print_and_invoice(): void
    {
        $folio = DB::table('pms_folios')->where('reservation_id', $this->res->id)->where('window', 1)->first();
        $this->actingAs($this->user)->get("/folyo/{$folio->id}/yazdir")->assertOk()->assertSee('Information copy')->assertSee('Yılmaz, Ahmet')->assertSee('13.450,00')->assertSee('This is not an invoice.');

        $html = $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}")->getContent();
        $this->assertStringContainsString('id="invoice-dialog"', $html);
        $this->assertStringContainsString('value="Yılmaz, Ahmet"', $html);

        $this->actingAs($this->user)->post("/folyo/{$folio->id}/fatura", ['bill_to_name' => 'Yılmaz Ltd.', 'bill_to_tax_office' => 'Girne', 'bill_to_tax_number' => '123456'])->assertRedirect();
        $invoice = DB::table('pms_invoices')->sole();
        $this->assertSame(['000001', 'Yılmaz Ltd.', 13450.0], [$invoice->invoice_number, $invoice->bill_to_name, (float) $invoice->total_gross]);
        $this->assertEqualsWithDelta(13450.0, (float) $invoice->total_net + (float) $invoice->total_tax, 0.01);
        $this->assertSame(0, DB::table('pms_postings')->where('folio_id', $folio->id)->where('amount', '>', 0)->whereNull('invoice_id')->whereNull('reversed_by_id')->count());
        $this->actingAs($this->user)->get("/fatura/{$invoice->id}")->assertOk()->assertSee('INVOICE')->assertSee('000001')->assertSee('Yılmaz Ltd.')->assertSee('13.450,00');

        // Tekrar faturalanmaz
        $this->actingAs($this->user)->post("/folyo/{$folio->id}/fatura", ['bill_to_name' => 'Yılmaz Ltd.'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('pms_invoices')->count());
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}")->assertSee('Invoice 000001');
    }

    public function test_account_customers_are_invoiced_in_logo(): void
    {
        $folio = DB::table('pms_folios')->where('reservation_id', $this->res->id)->where('window', 1)->first();
        // City Ledger ile kapanan folyo: cari hesap
        $this->actingAs($this->user)->post('/folyo/odeme', ['reservation_id' => $this->res->id, 'window' => 1, 'payment_code_id' => $this->tc('903'), 'amount' => '13450'])->assertSessionHas('status');
        $this->actingAs($this->user)->get("/folyo/rezervasyon/{$this->res->id}")->assertSee('Invoices of account customers are issued in Logo.');
        $this->actingAs($this->user)->post("/folyo/{$folio->id}/fatura", ['bill_to_name' => 'Örnek Holding'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('pms_invoices')->count());

        // Bilerek PMS'te
        $this->actingAs($this->user)->post("/folyo/{$folio->id}/fatura", ['bill_to_name' => 'Örnek Holding', 'force' => '1'])->assertRedirect();
        $this->assertSame(1, DB::table('pms_invoices')->count());

        // Şirket ödeyen folyo
        $company = DB::table('pms_profiles')->where('property_id', $this->property)->where('type', 'company')->value('id');
        DB::table('pms_folios')->where('reservation_id', $this->res->id)->where('window', 1)->update(['payee_profile_id' => $company]);
        $this->assertNotNull(app(InvoiceService::class)->accountReason(Folio::find($folio->id)));
    }
}
