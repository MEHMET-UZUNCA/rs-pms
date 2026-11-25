<?php

namespace App\Domain\Rates\Services;

use App\Domain\Rates\Models\RateAmount;
use App\Domain\Rates\Models\RateCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fiyat hesaplama (Fidelio fiyat kodu mantığı):
 *  - Tarihi kapsayan, haftanın gününe uyan satırlar arasından: gün tipine özel olan önce, sonra en dar aralık, eşitse en son eklenen.
 *  - Kişi sayısına göre fiyat (1–4 yetişkin), 4'ü aşan her yetişkin için ek yetişkin; çocuk bantları; ek yatak.
 *  - Fiyat tablosu boşsa türetilmiş fiyat (üst kod ± yüzde / tutar) ve yuvarlama.
 *  - Paket tutarı, fiyat kodunun paket elemanlarından (kişi / yetişkin / çocuk / oda başı).
 */
class RateCalculator
{
    public function quote(RateCode $rateCode, int $roomTypeId, CarbonInterface $date, int $adults, array $childBands = [], int $extraBeds = 0, int $depth = 0): RateQuote
    {
        $row = $this->findRow($rateCode, $roomTypeId, $date);
        $package = $this->packageAmount($rateCode, $adults, array_sum($childBands));

        if ($row) {
            return new RateQuote($this->priceFromRow($row, $adults, $childBands, $extraBeds), $package, $row->id, 'table');
        }

        if ($rateCode->parent_rate_code_id && $depth < 5) {
            $parent = $this->quote($rateCode->parent, $roomTypeId, $date, $adults, $childBands, $extraBeds, $depth + 1);
            if ($parent->found()) {
                $value = (float) $rateCode->derive_value;
                $amount = $rateCode->derive_type === 'amount' ? $parent->amount + $value : $parent->amount * (1 + $value / 100);

                return new RateQuote($this->round($amount, $rateCode->rounding), $package, $parent->rateAmountId, 'derived');
            }
        }

        return new RateQuote(null, $package, null, 'none');
    }

    private function findRow(RateCode $rateCode, int $roomTypeId, CarbonInterface $date): ?RateAmount
    {
        $day = $date->toDateString();
        $bit = 1 << ($date->dayOfWeekIso - 1);
        $dayTypeId = DB::table('pms_day_type_calendar')->where('property_id', $rateCode->property_id)->where('date', $day)->value('day_type_id');

        return RateAmount::query()
            ->where('rate_code_id', $rateCode->id)->where('room_type_id', $roomTypeId)
            ->where('date_from', '<=', $day)->where('date_to', '>=', $day)
            ->where(fn ($q) => $q->whereNull('day_type_id')->when($dayTypeId, fn ($q) => $q->orWhere('day_type_id', $dayTypeId)))
            ->get()
            ->filter(fn (RateAmount $r) => ($r->weekdays & $bit) !== 0)
            ->sortBy([
                fn ($a, $b) => ($b->day_type_id ? 1 : 0) <=> ($a->day_type_id ? 1 : 0),
                fn ($a, $b) => $a->date_from->diffInDays($a->date_to) <=> $b->date_from->diffInDays($b->date_to),
                fn ($a, $b) => $b->id <=> $a->id,
            ])
            ->first();
    }

    private function priceFromRow(RateAmount $row, int $adults, array $childBands, int $extraBeds): ?float
    {
        $prices = [1 => $row->price_1, 2 => $row->price_2, 3 => $row->price_3, 4 => $row->price_4];
        $extraAdult = (float) ($row->extra_adult ?? 0);
        $base = null;
        $baseAdults = 0;
        for ($n = min(max($adults, 1), 4); $n >= 1; $n--) {
            if ($prices[$n] !== null) {
                $base = (float) $prices[$n];
                $baseAdults = $n;
                break;
            }
        }
        if ($base === null) {
            return null;
        }

        $amount = $base + max(0, $adults - $baseAdults) * $extraAdult;
        foreach ([1, 2, 3] as $band) {
            $amount += (int) ($childBands[$band] ?? 0) * (float) ($row->{'child_band_'.$band} ?? 0);
        }
        $amount += $extraBeds * (float) ($row->extra_bed ?? 0);

        return round($amount, 2);
    }

    private function packageAmount(RateCode $rateCode, int $adults, int $children): float
    {
        if (! $rateCode->package_id) {
            return 0.0;
        }

        return round($rateCode->package->elements->sum(function ($e) use ($adults, $children) {
            return match ($e->calc) {
                'per_room' => (float) $e->amount_adult,
                'per_adult' => $adults * (float) $e->amount_adult,
                'per_child' => $children * (float) $e->amount_child,
                default => $adults * (float) $e->amount_adult + $children * (float) $e->amount_child,
            };
        }), 2);
    }

    private function round(float $amount, ?string $rounding): float
    {
        $step = in_array($rounding, ['1', '5', '10'], true) ? (int) $rounding : 0;

        return $step > 0 ? round($amount / $step) * $step : round($amount, 2);
    }
}
