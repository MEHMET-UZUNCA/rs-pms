<?php

namespace App\Domain\Core\Services;

use App\Domain\Rates\Models\TransactionCode;
use Carbon\CarbonInterface;

/**
 * İşlem koduna bağlı yüzde vergilerden net tutar ve vergi kırılımı hesaplar.
 * Fiyata dahil (is_tax_inclusive) kodlarda brüt tutardan geriye doğru, hariç kodlarda net tutar üzerine hesaplar.
 * Sabit tutarlı vergiler (konaklama vergisi gibi) ayrı kayıt olarak atılır; burada hesaplanmaz.
 */
class TaxCalculator
{
    /**
     * @return array{gross: float, net: float, tax: float, lines: array<int, array{tax_id:int, rate:float, base:float, amount:float}>}
     */
    public function split(TransactionCode $code, float $amount, ?CarbonInterface $date = null): array
    {
        $day = ($date ?? now())->toDateString();
        $taxes = $code->taxes()
            ->where('calc_type', 'percent')
            ->where(fn ($q) => $q->whereNull('pms_taxes.valid_from')->orWhere('pms_taxes.valid_from', '<=', $day))
            ->where(fn ($q) => $q->whereNull('pms_taxes.valid_to')->orWhere('pms_taxes.valid_to', '>=', $day))
            ->where(fn ($q) => $q->whereNull('pms_transaction_code_taxes.valid_from')->orWhere('pms_transaction_code_taxes.valid_from', '<=', $day))
            ->where(fn ($q) => $q->whereNull('pms_transaction_code_taxes.valid_to')->orWhere('pms_transaction_code_taxes.valid_to', '>=', $day))
            ->orderBy('pms_transaction_code_taxes.sequence')
            ->get();

        $totalRate = (float) $taxes->sum(fn ($t) => (float) $t->rate);
        if ($code->is_tax_inclusive) {
            $gross = round($amount, 2);
            $net = $totalRate > 0 ? round($gross / (1 + $totalRate / 100), 2) : $gross;
        } else {
            $net = round($amount, 2);
            $gross = round($net * (1 + $totalRate / 100), 2);
        }

        $lines = [];
        $remaining = round($gross - $net, 2);
        foreach ($taxes->values() as $i => $tax) {
            if ((float) $tax->rate == 0.0 && ! $tax->post_zero) {
                continue;
            }
            $isLast = $i === $taxes->count() - 1;
            $lineAmount = $isLast ? $remaining : round($net * (float) $tax->rate / 100, 2);
            $remaining = round($remaining - $lineAmount, 2);
            $lines[] = ['tax_id' => $tax->id, 'rate' => (float) $tax->rate, 'base' => $net, 'amount' => $lineAmount];
        }

        return ['gross' => $gross, 'net' => $net, 'tax' => round($gross - $net, 2), 'lines' => $lines];
    }
}
