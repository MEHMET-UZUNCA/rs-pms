<?php

namespace Tests\Feature\Domain;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Rates\Models\TransactionCode;
use Illuminate\Support\Facades\DB;

class PostingServiceTest extends DomainTestCase
{
    private PostingService $postings;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postings = app(PostingService::class);
        $vat = DB::table('pms_taxes')->insertGetId(['property_id' => $this->property, 'code' => 'KDV', 'name' => 'KDV', 'rate' => 10]);
        DB::table('pms_transaction_code_taxes')->where('transaction_code_id', $this->code('100'))->delete();   // seeder'ın %5'i yerine bu testin %10'u
        DB::table('pms_transaction_code_taxes')->insert(['transaction_code_id' => $this->code('100'), 'tax_id' => $vat]);
        $res = $this->reservation($this->roomType(), $this->rateCode(), '2026-12-01', 2, 'in_house');
        $this->folio = Folio::create(['property_id' => $this->property, 'folio_number' => 'F-1', 'reservation_id' => $res, 'window' => 1, 'opened_business_date' => '2026-12-01']);
    }

    private function tc(string $code): TransactionCode
    {
        return TransactionCode::find($this->code($code));
    }

    public function test_charge_splits_tax_and_updates_balance_on_business_date(): void
    {
        $posting = $this->postings->charge($this->folio, $this->tc('100'), 4400);

        $this->assertSame('2026-12-01', $posting->business_date->toDateString());
        $this->assertSame('4000.00', $posting->net_amount);
        $this->assertSame('400.00', $posting->tax_amount);
        $this->assertSame(1, $posting->taxes()->count());
        $this->assertSame('4400.00', $this->folio->fresh()->balance);
    }

    public function test_payment_is_negative_and_brings_balance_to_zero(): void
    {
        $this->postings->charge($this->folio, $this->tc('100'), 4400);
        $payment = $this->postings->payment($this->folio, $this->tc('900'), 4400);

        $this->assertSame('-4400.00', $payment->amount);
        $this->assertSame('payment', $payment->kind);
        $this->assertSame('0.00', $this->folio->fresh()->balance);
    }

    public function test_reversal_mirrors_taxes_and_cannot_be_repeated(): void
    {
        $posting = $this->postings->charge($this->folio, $this->tc('100'), 4400);
        $reversal = $this->postings->reverse($posting, null, 'Yanlış oda');

        $this->assertSame('-4400.00', $reversal->amount);
        $this->assertSame('-400.00', $reversal->tax_amount);
        $this->assertSame($reversal->id, $posting->fresh()->reversed_by_id);
        $this->assertSame('0.00', $this->folio->fresh()->balance);

        $this->expectException(PostingException::class);
        $this->postings->reverse($posting->fresh());
    }

    public function test_transfer_moves_charge_and_keeps_origin(): void
    {
        $posting = $this->postings->charge($this->folio, $this->tc('320'), 1250);
        $company = Folio::create(['property_id' => $this->property, 'folio_number' => 'F-2', 'reservation_id' => $this->folio->reservation_id, 'window' => 2, 'opened_business_date' => '2026-12-01']);

        $moved = $this->postings->transfer($posting, $company);

        $this->assertSame('0.00', $this->folio->fresh()->balance);
        $this->assertSame('1250.00', $company->fresh()->balance);
        $this->assertSame($posting->id, $moved->transfer_from_id);
        $this->assertSame($this->folio->id, $moved->origin_folio_id);
        $this->assertSame($this->folio->reservation_id, $moved->origin_reservation_id);
    }

    public function test_same_external_request_is_posted_once(): void
    {
        $a = $this->postings->charge($this->folio, $this->tc('320'), 900, 1, 'Simphony çek 1045', ['source' => 'simphony', 'idempotency_key' => 'rvc3-1045']);
        $b = $this->postings->charge($this->folio, $this->tc('320'), 900, 1, 'Simphony çek 1045', ['source' => 'simphony', 'idempotency_key' => 'rvc3-1045']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame('900.00', $this->folio->fresh()->balance);
    }

    public function test_closed_folio_and_wrong_code_types_are_rejected(): void
    {
        try {
            $this->postings->charge($this->folio, $this->tc('900'), 100);
            $this->fail('Ödeme kodu harcama olarak kabul edildi');
        } catch (PostingException) {
        }

        $this->folio->update(['status' => 'closed']);
        $this->expectException(PostingException::class);
        $this->postings->charge($this->folio, $this->tc('100'), 100);
    }

    public function test_posting_on_a_closed_business_date_is_rejected(): void
    {
        DB::table('pms_business_dates')->insert(['property_id' => $this->property, 'business_date' => '2026-12-01', 'status' => 'closed']);
        $this->expectException(PostingException::class);
        $this->postings->charge($this->folio, $this->tc('100'), 100);
    }
}
