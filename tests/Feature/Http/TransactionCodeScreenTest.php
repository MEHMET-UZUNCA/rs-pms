<?php

namespace Tests\Feature\Http;

use App\Domain\Core\Services\TaxCalculator;
use App\Domain\Rates\Models\TransactionCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class TransactionCodeScreenTest extends DomainTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->admin->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function tc(string $no): TransactionCode
    {
        return TransactionCode::where('property_id', $this->property)->where('code', $no)->firstOrFail();
    }

    private function tax(string $code): int
    {
        return (int) DB::table('pms_taxes')->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'code' => '599', 'category' => 'oth', 'name' => 'Deneme geliri', 'type' => 'revenue',
            'group_id' => DB::table('pms_transaction_code_groups')->where('property_id', $this->property)->where('code', '50')->value('id'),
            'is_active' => '1', 'manual_post_allowed' => '1', 'is_tax_inclusive' => '1',
            'taxes' => [['tax_id' => $this->tax('VAT5'), 'valid_from' => '2001-01-01', 'valid_to' => '']],
        ], $override);
    }

    public function test_list_matches_fidelio_order_and_shows_tax_and_outlet(): void
    {
        $this->actingAs($this->admin)->get('/ayarlar/departman-kodlari')->assertOk()
            ->assertSeeInOrder(['>100<', '>102<', '>103<', '>115<', '>116<', '>117<', '>120<'], false)   // Fidelio listesindeki sıra
            ->assertSee('Room Supplement')->assertSee('Byblos');

        $html = $this->actingAs($this->admin)->get('/ayarlar/departman-kodlari?q=510')->assertOk()->getContent();
        $this->assertStringContainsString('Laundry', $html);
        $this->assertStringContainsString('%16', $html);
        $this->assertStringNotContainsString('Room Supplement', $html);

        $group = DB::table('pms_transaction_code_groups')->where('property_id', $this->property)->where('code', '95')->value('id');
        $this->actingAs($this->admin)->get("/ayarlar/departman-kodlari?grup={$group}")->assertSee('Swiss Franc')->assertDontSee('Room Supplement');
    }

    public function test_edit_form_shows_fidelio_fields_with_dated_tax(): void
    {
        $this->actingAs($this->admin)->get('/ayarlar/departman-kodlari/'.$this->tc('100')->id.'/duzenle')->assertOk()
            ->assertSee('Adjustment Dept')->assertSee('2001-01-01')->assertSee('VAT5 — %5')
            ->assertSee('<option value="'.$this->tc('102')->id.'" selected>102 DISC Room</option>', false);   // 100 → 102
    }

    public function test_new_code_is_saved_with_dated_taxes_and_flags(): void
    {
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload([
            'folio_text' => 'Deneme', 'non_hotel_revenue' => '1', 'disabled_in_ar' => '1', 'credit_limit' => '5000',
            'taxes' => [
                ['tax_id' => $this->tax('VAT5'), 'valid_from' => '2001-01-01', 'valid_to' => '2026-12-31'],
                ['tax_id' => $this->tax('VAT10'), 'valid_from' => '2027-01-01', 'valid_to' => ''],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $code = $this->tc('599');
        $this->assertSame('OTH', $code->category);
        $this->assertTrue($code->non_hotel_revenue);
        $this->assertTrue($code->disabled_in_ar);
        $this->assertSame('5000.00', $code->credit_limit);

        // Tarihli vergi: 31.12.2026'ya kadar %5, 1.1.2027'den itibaren %10
        $calc = app(TaxCalculator::class);
        $this->assertSame(1050.0, $calc->split($code, 1050, CarbonImmutable::parse('2026-12-31'))['gross']);
        $this->assertSame(1000.0, $calc->split($code, 1050, CarbonImmutable::parse('2026-12-31'))['net']);
        $this->assertSame(954.55, $calc->split($code, 1050, CarbonImmutable::parse('2027-01-01'))['net']);
    }

    public function test_validation_rejects_duplicate_number_overlapping_tax_and_payment_without_kind(): void
    {
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload(['code' => '100']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload(['code' => '12a']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload(['type' => 'payment']))->assertSessionHasErrors('payment_kind');
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload(['taxes' => [
            ['tax_id' => $this->tax('VAT5'), 'valid_from' => '2001-01-01', 'valid_to' => ''],
            ['tax_id' => $this->tax('VAT5'), 'valid_from' => '2020-01-01', 'valid_to' => ''],
        ]]))->assertSessionHasErrors(['taxes' => 'Aynı vergi çakışan tarihlerle iki kez girilmiş.']);
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload(['taxes' => [
            ['tax_id' => $this->tax('VAT5'), 'valid_from' => '2027-01-01', 'valid_to' => '2026-01-01'],
        ]]))->assertSessionHasErrors('taxes');
        $this->assertNull(TransactionCode::where('code', '599')->first());
    }

    public function test_update_changes_spa_tax_from_a_date_without_touching_history(): void
    {
        $spa = $this->tc('513');
        $this->actingAs($this->admin)->put("/ayarlar/departman-kodlari/{$spa->id}", $this->payload([
            'code' => '513', 'name' => 'SAUNA-MASSAGE', 'group_id' => $spa->group_id, 'outlet_id' => $spa->outlet_id,
            'taxes' => [
                ['tax_id' => $this->tax('VAT5'), 'valid_from' => '2001-01-01', 'valid_to' => '2026-11-30'],
                ['tax_id' => $this->tax('VAT16'), 'valid_from' => '2026-12-01', 'valid_to' => ''],
            ],
        ]))->assertSessionHasNoErrors();

        $spa->refresh();
        $this->assertSame('SPA', $spa->outlet->code);   // outlet bağlantısı korunur
        $calc = app(TaxCalculator::class);
        $this->assertSame(5.0, $calc->split($spa, 105, CarbonImmutable::parse('2026-11-30'))['tax']);
        $this->assertSame(16.0, $calc->split($spa, 116, CarbonImmutable::parse('2026-12-01'))['tax']);
    }

    public function test_used_code_cannot_be_deleted_unused_can_and_up_moves(): void
    {
        $this->actingAs($this->admin)->delete('/ayarlar/departman-kodlari/'.$this->tc('100')->id)->assertSessionHas('error');   // 102'nin asıl kodu
        $this->assertNotNull(TransactionCode::where('code', '100')->first());

        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari', $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete('/ayarlar/departman-kodlari/'.$this->tc('599')->id)->assertSessionHas('status');
        $this->assertNull(TransactionCode::withTrashed()->where('property_id', $this->property)->where('code', '599')->first());

        $this->assertLessThan($this->tc('103')->sort, $this->tc('102')->sort);
        $this->actingAs($this->admin)->post('/ayarlar/departman-kodlari/'.$this->tc('103')->id.'/tasi/up')->assertRedirect();
        $this->assertLessThan($this->tc('102')->sort, $this->tc('103')->sort);   // 103 artık 102'nin üstünde
        $this->assertLessThan($this->tc('103')->sort, $this->tc('100')->sort);
    }

    public function test_copy_prefills_without_number_and_clerk_is_forbidden(): void
    {
        $this->actingAs($this->admin)->get('/ayarlar/departman-kodlari/'.$this->tc('320')->id.'/kopyala')->assertOk()
            ->assertSee('Kopyala: 320')->assertSee('value="FOOD  Cafe Rocks"', false);

        $clerk = User::create(['username' => 'ON', 'name' => 'Ön büro', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->insertGetId(['code' => 'reception', 'name' => 'Resepsiyon'])]);
        $this->actingAs($clerk)->get('/ayarlar/departman-kodlari')->assertForbidden();
    }
}
