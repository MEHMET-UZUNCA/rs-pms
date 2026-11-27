<?php

namespace Tests\Feature\Http;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Rates\Models\TransactionCode;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class FinancialAccountTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    public function test_new_pm_account_opens_today_with_fidelio_fields(): void
    {
        $this->actingAs($this->user)->get('/hesaplar/yeni')->assertOk()
            ->assertSee('New Financial Account')->assertSee('PM — PM Accounts')->assertSee('No Post');

        $response = $this->actingAs($this->user)->post('/hesaplar', [
            'folio_group_id' => $this->id('pms_folio_groups', 'PM'), 'valid_from' => 't', 'valid_until' => '+30',
            'name' => 'Casino hospitality', 'market_segment_id' => $this->id('pms_market_segments', 'COR'), 'credit_limit' => '99.999,00',
            'currency_code' => 'TRY', 'billing_info' => 'Monthly invoice', 'notes' => 'GM approval', 'no_post' => '0',
        ]);

        $fa = Folio::where('name', 'Casino hospitality')->firstOrFail();
        $response->assertRedirect(route('billing.account', $fa));
        $this->assertSame(['2026-12-01', '2026-12-31', '2026-12-01', 'open', 'house_account'],
            [$fa->valid_from->toDateString(), $fa->valid_until->toDateString(), $fa->actual_from->toDateString(), $fa->status, $fa->type]);
        $this->assertSame('99999.00', (string) $fa->credit_limit);
        $this->assertSame($this->user->id, (int) $fa->opened_by);
        $this->assertNull($fa->reservation_id);

        $this->actingAs($this->user)->get('/rezervasyon?f=fa')->assertSee('Casino hospitality')->assertSee(route('accounts.create'), false);
        $this->actingAs($this->user)->get(route('billing.account', $fa))->assertSee(route('accounts.edit', $fa), false);
    }

    public function test_future_account_is_expected_and_custom_number_is_unique(): void
    {
        $this->actingAs($this->user)->post('/hesaplar', ['folio_number' => '9950', 'valid_from' => '+2', 'name' => 'Wedding group'])->assertSessionHasNoErrors();
        $fa = Folio::where('folio_number', '9950')->firstOrFail();
        $this->assertSame('2026-12-03', $fa->valid_from->toDateString());
        $this->assertNull($fa->actual_from);                                         // beklenen hesap

        $this->actingAs($this->user)->post('/hesaplar', ['folio_number' => '9950', 'name' => 'x'])->assertSessionHasErrors('folio_number');
        $this->actingAs($this->user)->post('/hesaplar', ['valid_from' => '+5', 'valid_until' => '+1', 'name' => 'x'])->assertSessionHasErrors('valid_until');
        $this->actingAs($this->user)->post('/hesaplar', ['valid_from' => 'abc', 'name' => 'x'])->assertSessionHasErrors('valid_from');
    }

    public function test_edit_and_no_post_blocks_charges_but_not_payments(): void
    {
        $fa = Folio::where('folio_number', 'FA-DEMO-1')->firstOrFail();
        $this->actingAs($this->user)->get("/hesaplar/{$fa->id}/duzenle")->assertOk()->assertSee('value="FA-DEMO-1"', false);
        $this->actingAs($this->user)->put("/hesaplar/{$fa->id}", ['folio_number' => 'FA-DEMO-1', 'name' => 'Front office cash', 'no_post' => '1',
            'valid_from' => $fa->valid_from->format('d.m.Y'), 'valid_until' => $fa->valid_until->format('d.m.Y')])->assertSessionHasNoErrors();
        $fa->refresh();
        $this->assertTrue($fa->no_post);
        $this->assertSame('Front office cash', $fa->name);

        $postings = app(PostingService::class);
        $code = fn (string $c) => TransactionCode::where('property_id', $this->property)->where('code', $c)->firstOrFail();
        $postings->payment($fa, $code('900'), 100);
        $this->expectException(PostingException::class);
        $postings->charge($fa, $code('630'), 50);
    }

    public function test_reservation_folio_cannot_be_edited_here_and_permission_needed(): void
    {
        $guestFolio = Folio::whereNotNull('reservation_id')->firstOrFail();
        $this->actingAs($this->user)->get("/hesaplar/{$guestFolio->id}/duzenle")->assertNotFound();

        $clerk = User::create(['username' => 'FOA1', 'name' => 'Clerk', 'email' => 'c@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($clerk)->get('/hesaplar/yeni')->assertForbidden();
    }
}
