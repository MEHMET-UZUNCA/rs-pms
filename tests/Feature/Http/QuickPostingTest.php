<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class QuickPostingTest extends DomainTestCase
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

    private function manualCode(): string
    {
        return (string) DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('type', 'revenue')->where('is_active', true)
            ->where('manual_post_allowed', true)->where('disabled_in_billing', false)->orderBy('id')->value('code');
    }

    public function test_batch_posting_is_all_or_nothing(): void
    {
        $guests = Reservation::where('status', 'in_house')->whereNotNull('room_id')->with('room')->take(2)->get();
        $code = $this->manualCode();
        $this->actingAs($this->user)->get('/kasa-islem/kayit')->assertOk()->assertSee('data-quick-posting', false);

        // Bir satır hatalı: hiçbiri yazılmaz
        $before = DB::table('pms_postings')->count();
        $this->actingAs($this->user)->post('/kasa-islem/kayit', ['lines' => [
            ['target' => $guests[0]->room->number, 'code' => $code, 'amount' => '120', 'qty' => '2'],
            ['target' => '0000', 'code' => $code, 'amount' => '50'],
        ]])->assertSessionHas('error')->assertSessionHas('line_errors', fn ($e) => array_keys($e) === [2]);
        $this->assertSame($before, DB::table('pms_postings')->count());

        $this->actingAs($this->user)->post('/kasa-islem/kayit', ['lines' => [
            ['target' => $guests[0]->room->number, 'code' => $code, 'amount' => '120', 'qty' => '2', 'reference' => 'MB-15'],
            ['target' => $guests[1]->room->number, 'code' => $code, 'amount' => '35,50', 'description' => 'Minibar'],
            ['target' => '', 'code' => '', 'amount' => ''],
        ]])->assertSessionHas('status');
        $rows = DB::table('pms_postings')->where('source', 'quick_post')->orderBy('id')->get();
        $this->assertSame([240.0, 35.5], $rows->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame('MB-15', $rows[0]->reference);
        $this->assertNotNull($rows[0]->cashier_shift_id);
        $this->actingAs($this->user)->get('/kasa-islem/kayit')->assertSee('MB-15');
    }

    public function test_passer_by_open_post_pay_and_close(): void
    {
        $this->actingAs($this->user)->get('/kasa-islem/passer-by')->assertOk()->assertSee('data-passer-by', false);
        $this->actingAs($this->user)->post('/kasa-islem/passer-by', ['name' => 'Spa misafiri'])->assertRedirect();
        $folio = DB::table('pms_folios')->where('type', 'passer_by')->latest('id')->first();
        $this->assertSame(['Spa misafiri', 'open'], [$folio->name, $folio->status]);
        $this->actingAs($this->user)->get("/folyo/hesap/{$folio->id}")->assertOk()->assertSee(__('pms.passer.close'));

        // Accountsuz numarayla hızlı kayıt da yapılabilir
        $this->actingAs($this->user)->post('/kasa-islem/kayit', ['lines' => [['target' => $folio->folio_number, 'code' => $this->manualCode(), 'amount' => '500']]])->assertSessionHas('status');
        $this->actingAs($this->user)->post("/folyo/hesap/{$folio->id}/kapat")->assertSessionHas('error');   // bakiye var
        $cash = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('type', 'payment')->where('payment_kind', 'cash')->value('id');
        $this->actingAs($this->user)->post('/folyo/odeme', ['folio_id' => $folio->id, 'payment_code_id' => $cash, 'amount' => '500'])->assertSessionHas('status');
        $this->actingAs($this->user)->post("/folyo/hesap/{$folio->id}/kapat")->assertRedirect(route('cashiering.passer-by'));
        $this->assertSame('closed', DB::table('pms_folios')->where('id', $folio->id)->value('status'));
        $this->actingAs($this->user)->get('/kasa-islem/passer-by')->assertSee($folio->folio_number);   // bugün kapananlar listede
    }
}
