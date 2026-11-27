<?php

namespace App\Domain\Cashiering\Services;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Models\Posting;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\TaxCalculator;
use App\Domain\Rates\Models\TransactionCode;
use Illuminate\Support\Facades\DB;

/**
 * Folyo kaydı (posting) — sistemin mali çekirdeği.
 *  - Her kayıt tesisin açık iş gününe yazılır; folyo satır kilidiyle bakiye aynı işlemde güncellenir.
 *  - Harcama +, ödeme − tutarla tutulur; vergi kırılımı işlem kodunun vergilerinden hesaplanır.
 *  - Düzeltme yalnız ters kayıtla yapılır (silme/değiştirme veritabanında da engellidir).
 *  - Transfer: kaynakta ters kayıt + hedefte yeni kayıt; ilk sahip (origin_*) bilgisi korunur.
 *  - idempotency_key aynı dış isteğin iki kez işlenmesini engeller (Simphony, RS ONE).
 */
class PostingService
{
    public function __construct(private readonly TaxCalculator $taxes) {}

    /**
     * Harcama kaydı. $amount brüt, pozitif (iade/indirim için negatif verilebilir).
     *
     * @param  array<string,mixed>  $attributes  reference, note, stay_date, reservation_night_id, outlet_id, source, cashier_shift_id, posted_by, idempotency_key …
     */
    public function charge(Folio $folio, TransactionCode $code, float $amount, float $quantity = 1, ?string $description = null, array $attributes = []): Posting
    {
        if (in_array($code->type, ['payment', 'deposit'], true)) {
            throw new PostingException(__('pms.err.payment_as_charge', ['code' => $code->code]));
        }
        if ($folio->no_post) {
            throw new PostingException(__('pms.fa.err_no_post', ['number' => $folio->folio_number]));
        }

        return $this->write($folio, $code, round($amount * $quantity, 2), $quantity, $description, array_merge(['kind' => 'charge'], $attributes));
    }

    /** Ödeme kaydı. $amount pozitif verilir, folyoya eksi olarak yazılır. */
    public function payment(Folio $folio, TransactionCode $code, float $amount, ?string $description = null, array $attributes = []): Posting
    {
        if (! in_array($code->type, ['payment', 'deposit'], true)) {
            throw new PostingException(__('pms.err.not_payment', ['code' => $code->code]));
        }
        $kind = $code->type === 'deposit' ? 'deposit' : 'payment';

        return $this->write($folio, $code, -round($amount, 2), 1, $description, array_merge(['kind' => $kind, 'ledger' => $kind === 'deposit' ? 'deposit' : 'guest'], $attributes));
    }

    /** Ters kayıt: aynı tutarın eksisi, orijinale bağlı. Bir kayıt yalnız bir kez ters çevrilebilir. */
    public function reverse(Posting $posting, ?int $reasonCodeId = null, ?string $note = null, ?int $userId = null): Posting
    {
        return DB::transaction(function () use ($posting, $reasonCodeId, $note, $userId) {
            $original = Posting::query()->whereKey($posting->id)->lockForUpdate()->firstOrFail();
            if ($original->reversed_by_id) {
                throw new PostingException(__('pms.err.already_reversed'));
            }
            if ($original->reversal_of_id) {
                throw new PostingException(__('pms.err.reverse_reversal'));
            }

            $reversal = $this->write($original->folio, $original->transactionCode, -(float) $original->amount, (float) $original->quantity,
                'İptal: '.$original->description, [
                    'kind' => 'adjustment', 'ledger' => $original->ledger, 'reversal_of_id' => $original->id, 'reason_code_id' => $reasonCodeId,
                    'note' => $note, 'posted_by' => $userId, 'stay_date' => $original->stay_date, 'outlet_id' => $original->outlet_id,
                    'market_segment_id' => $original->market_segment_id, 'skip_tax' => true, 'tax_from' => $original,
                ]);
            $original->update(['reversed_by_id' => $reversal->id]);

            return $reversal;
        });
    }

    /** Kaydı başka folyoya aktarır; kaynakta ters kayıt, hedefte yeni kayıt (ilk sahip bilgisiyle). */
    public function transfer(Posting $posting, Folio $target, ?int $userId = null): Posting
    {
        return DB::transaction(function () use ($posting, $target, $userId) {
            $original = Posting::query()->whereKey($posting->id)->lockForUpdate()->firstOrFail();
            if ($original->reversed_by_id) {
                throw new PostingException(__('pms.err.transfer_reversed'));
            }
            if ((int) $original->folio_id === (int) $target->id) {
                throw new PostingException(__('pms.err.same_folio'));
            }

            $out = $this->write($original->folio, $original->transactionCode, -(float) $original->amount, (float) $original->quantity,
                'Aktarıldı: '.$original->description, [
                    'kind' => 'transfer', 'ledger' => $original->ledger, 'reversal_of_id' => $original->id, 'posted_by' => $userId,
                    'stay_date' => $original->stay_date, 'skip_tax' => true, 'tax_from' => $original,
                ]);
            $original->update(['reversed_by_id' => $out->id]);

            return $this->write($target, $original->transactionCode, (float) $original->amount, (float) $original->quantity, $original->description, [
                'kind' => $original->kind, 'ledger' => $original->ledger, 'transfer_from_id' => $original->id, 'posted_by' => $userId,
                'stay_date' => $original->stay_date, 'outlet_id' => $original->outlet_id, 'market_segment_id' => $original->market_segment_id,
                'origin_reservation_id' => $original->origin_reservation_id ?? $original->reservation_id,
                'origin_folio_id' => $original->origin_folio_id ?? $original->folio_id,
                'origin_profile_id' => $original->origin_profile_id ?? $original->folio->payee_profile_id,
                'skip_tax' => true, 'tax_from' => $original,
            ]);
        });
    }

    private function write(Folio $folio, TransactionCode $code, float $amount, float $quantity, ?string $description, array $attributes): Posting
    {
        return DB::transaction(function () use ($folio, $code, $amount, $quantity, $description, $attributes) {
            if (! empty($attributes['idempotency_key'])) {
                $existing = Posting::query()->where('idempotency_key', $attributes['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $locked = Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'open') {
                throw new PostingException(__('pms.err.folio_closed', ['folio' => $locked->folio_number]));
            }
            $property = Property::query()->findOrFail($locked->property_id);
            $businessDate = $property->current_business_date;
            $closed = DB::table('pms_business_dates')->where('property_id', $property->id)
                ->where('business_date', $businessDate->toDateString())->where('status', 'closed')->exists();
            if ($closed) {
                throw new PostingException(__('pms.err.day_closed'));
            }

            [$net, $tax, $lines] = $this->taxParts($code, $amount, $attributes['tax_from'] ?? null, $businessDate);
            unset($attributes['skip_tax'], $attributes['tax_from']);

            $posting = Posting::create(array_merge([
                'property_id' => $property->id,
                'business_date' => $businessDate->toDateString(),
                'folio_id' => $locked->id,
                'reservation_id' => $locked->reservation_id,
                'room_id' => $locked->reservation?->room_id,
                'transaction_code_id' => $code->id,
                'ledger' => 'guest',
                'description' => $description ?? $code->name,
                'quantity' => $quantity,
                'unit_price' => $quantity != 0 ? round($net / $quantity, 2) : 0,
                'gross_unit_price' => $quantity != 0 ? round($amount / $quantity, 2) : 0,
                'amount' => $amount,
                'net_amount' => $net,
                'tax_amount' => $tax,
                'source' => 'manual',
            ], $attributes));

            foreach ($lines as $line) {
                $posting->taxes()->create(['tax_id' => $line['tax_id'], 'rate' => $line['rate'], 'base_amount' => $line['base'], 'tax_amount' => $line['amount']]);
            }

            $locked->update(['balance' => round((float) $locked->balance + $amount, 2)]);
            $folio->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $posting;
        });
    }

    /** @return array{0: float, 1: float, 2: array<int, array{tax_id:int, rate:float, base:float, amount:float}>} */
    private function taxParts(TransactionCode $code, float $amount, ?Posting $mirror, $date): array
    {
        if ($mirror) {
            $factor = (float) $mirror->amount != 0.0 ? $amount / (float) $mirror->amount : 0;
            $lines = $mirror->taxes->map(fn ($t) => [
                'tax_id' => $t->tax_id, 'rate' => (float) $t->rate, 'base' => round((float) $t->base_amount * $factor, 2), 'amount' => round((float) $t->tax_amount * $factor, 2),
            ])->all();

            return [round((float) $mirror->net_amount * $factor, 2), round((float) $mirror->tax_amount * $factor, 2), $lines];
        }
        if (in_array($code->type, ['payment', 'deposit', 'paid_out', 'ar_transfer', 'internal'], true)) {
            return [$amount, 0.0, []];
        }
        $split = $this->taxes->split($code, abs($amount), $date);
        $sign = $amount < 0 ? -1 : 1;
        $lines = array_map(fn ($l) => array_merge($l, ['base' => $sign * $l['base'], 'amount' => $sign * $l['amount']]), $split['lines']);

        return [$sign * $split['net'], $sign * $split['tax'], $lines];
    }
}
