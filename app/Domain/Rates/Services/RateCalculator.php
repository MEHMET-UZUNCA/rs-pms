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
    public function quote(RateCode $rateCode, int $roomTypeId, CarbonInterface $date, int $adults, array $childBands = [], int $extraBeds = 0, int $depth = 0, ?string $roomNumber = null): RateQuote
    {
        $row = $this->findRow($rateCode, $roomTypeId, $date, $roomNumber);
        $package = $this->packageAmount($rateCode, $adults, array_sum($childBands));

        if ($row) {
            $weekend = ((int) $rateCode->weekend_days & (1 << ($date->dayOfWeekIso - 1))) !== 0;

            return new RateQuote($this->priceFromRow($row, $adults, $childBands, $extraBeds, $weekend), $package, $row->id, 'table');
        }

        if ($rateCode->parent_rate_code_id && $depth < 5) {
            $parent = $this->quote($rateCode->parent, $roomTypeId, $date, $adults, $childBands, $extraBeds, $depth + 1, $roomNumber);
            if ($parent->found()) {
                $value = (float) $rateCode->derive_value;
                $amount = $rateCode->derive_type === 'amount' ? $parent->amount + $value : $parent->amount * (1 + $value / 100);

                return new RateQuote($this->round($amount, $rateCode->rounding), $package, $parent->rateAmountId, 'derived');
            }
        }

        return new RateQuote(null, $package, null, 'none');
    }

    /**
     * Fidelio Rate Details seçimi: satır, oda tipini kapsamalı (kendi oda tipi, oda tipi listesi ya da hiç oda tipi yoksa hepsi);
     * oda numarası listesi varsa yalnız o odalar için geçerlidir. Öncelik: belirli oda > gün tipi > belirli oda tipi > en dar aralık > en son eklenen.
     */
    private function findRow(RateCode $rateCode, int $roomTypeId, CarbonInterface $date, ?string $roomNumber = null): ?RateAmount
    {
        $day = $date->toDateString();
        $bit = 1 << ($date->dayOfWeekIso - 1);
        $dayTypeId = DB::table('pms_day_type_calendar')->where('property_id', $rateCode->property_id)->where('date', $day)->value('day_type_id');

        return RateAmount::query()
            ->where('rate_code_id', $rateCode->id)
            ->where(fn ($q) => $q->where('room_type_id', $roomTypeId)
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('pms_rate_amount_room_types as t')->whereColumn('t.rate_amount_id', 'pms_rate_amounts.id')->where('t.room_type_id', $roomTypeId))
                ->orWhere(fn ($all) => $all->whereNull('room_type_id')->whereNotExists(fn ($e) => $e->selectRaw('1')->from('pms_rate_amount_room_types as t')->whereColumn('t.rate_amount_id', 'pms_rate_amounts.id'))))
            ->where('date_from', '<=', $day)->where('date_to', '>=', $day)
            ->where(fn ($q) => $q->whereNull('day_type_id')->when($dayTypeId, fn ($q) => $q->orWhere('day_type_id', $dayTypeId)))
            ->get()
            ->filter(fn (RateAmount $r) => ($r->weekdays & $bit) !== 0)
            ->filter(fn (RateAmount $r) => ! $r->room_numbers || ($roomNumber !== null && in_array($roomNumber, self::rooms($r->room_numbers), true)))
            ->sortBy([
                fn ($a, $b) => ($b->room_numbers ? 1 : 0) <=> ($a->room_numbers ? 1 : 0),
                fn ($a, $b) => ($b->day_type_id ? 1 : 0) <=> ($a->day_type_id ? 1 : 0),
                fn ($a, $b) => ($b->room_type_id || $b->roomTypes()->exists() ? 1 : 0) <=> ($a->room_type_id || $a->roomTypes()->exists() ? 1 : 0),
                fn ($a, $b) => $a->date_from->diffInDays($a->date_to) <=> $b->date_from->diffInDays($b->date_to),
                fn ($a, $b) => $b->id <=> $a->id,
            ])
            ->first();
    }

    /** "1322, 2410" → ["1322", "2410"] */
    public static function rooms(?string $list): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $list))));
    }

    /**
     * Fidelio Prices: yetişkin sayısının fiyatı (1–4), yoksa "Default" fiyatı, yoksa en yakın alt kişi fiyatı + ek yetişkin;
     * hafta sonu gününde hafta sonu fiyatı tanımlıysa o kullanılır. Çocuk yaş kategorisi başına fiyat; ek yatak.
     */
    private function priceFromRow(RateAmount $row, int $adults, array $childBands, int $extraBeds, bool $weekend = false): ?float
    {
        $pick = fn (string $column) => $weekend && $row->{'weekend_'.$column} !== null ? $row->{'weekend_'.$column} : $row->{$column};
        $adults = max($adults, 1);
        $extraAdult = (float) ($row->extra_adult ?? 0);
        $base = null;
        $baseAdults = 0;
        if ($adults <= 4 && $pick('price_'.$adults) !== null) {
            [$base, $baseAdults] = [(float) $pick('price_'.$adults), $adults];
        } elseif ($pick('price_default') !== null) {
            [$base, $baseAdults] = [(float) $pick('price_default'), $adults];
        } else {
            for ($n = min($adults, 4); $n >= 1; $n--) {
                if ($pick('price_'.$n) !== null) {
                    [$base, $baseAdults] = [(float) $pick('price_'.$n), $n];
                    break;
                }
            }
        }
        if ($base === null) {
            return null;
        }

        $amount = $base + max(0, $adults - $baseAdults) * $extraAdult;
        foreach ([1, 2, 3] as $band) {
            $amount += (int) ($childBands[$band] ?? 0) * (float) ($pick('child_band_'.$band) ?? 0);
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
