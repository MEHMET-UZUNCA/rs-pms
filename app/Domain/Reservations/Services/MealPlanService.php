<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Support\ReservationOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Multiboard / Meal Plan: rezervasyonun gece gece pansiyonu ve mutfak için gün gün öğün sayıları.
 *
 * Öğün kuralı (Fidelio Meal Plan defaults, Ayarlar → Rezervasyon seçenekleri):
 *  - Giriş günü: yalnız "giriş günü" işaretli öğünler (Rocks: akşam yemeği), o gecenin pansiyonu içeriyorsa.
 *  - Ara günler: kahvaltı bir önceki gecenin pansiyonundan, öğle ve akşam o gecenin pansiyonundan (kalış ortasında değişiklik doğru sayılır).
 *  - Çıkış günü: yalnız "çıkış günü" işaretli öğünler (Rocks: kahvaltı, öğle), son gecenin pansiyonu içeriyorsa.
 *  - Günübirlik: "günübirlik" işaretli öğünler.
 */
class MealPlanService
{
    public const MEALS = ['breakfast', 'lunch', 'dinner'];

    /** Seçili gecelerin pansiyonunu değiştirir (Fidelio Meal Plan). Fiyat değişmez; fark varsa ayrıca kayıt atılır. */
    public function changeBoard(Reservation $reservation, string $from, string $to, ?int $packageId): int
    {
        if (in_array($reservation->status, ['cancelled', 'checked_out', 'no_show'], true)) {
            throw new RuntimeException(__('pms.meal.err_status'));
        }
        if ($packageId && ! DB::table('pms_packages')->where('id', $packageId)->where('property_id', $reservation->property_id)->where('type', 'board')->exists()) {
            throw new RuntimeException(__('pms.meal.err_board'));
        }

        return $reservation->nightRows()->where('status', '!=', 'cancelled')->whereBetween('stay_date', [$from, $to])->update(['package_id' => $packageId]);
    }

    /**
     * Gün gün öğün sayıları: [tarih => [meal => [adults, children]]], ayrıca pansiyon bazında gece sayısı.
     *
     * @return array<string, array<string, array{adults: int, children: int}>>
     */
    public function forecast(int $propertyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $options = ReservationOptions::get($propertyId);
        $boards = DB::table('pms_packages')->where('property_id', $propertyId)->where('type', 'board')->get()->keyBy('id');
        $meals = fn (?int $packageId) => $packageId && isset($boards[$packageId])
            ? ['breakfast' => (bool) $boards[$packageId]->includes_breakfast, 'lunch' => (bool) $boards[$packageId]->includes_lunch, 'dinner' => (bool) $boards[$packageId]->includes_dinner]
            : ['breakfast' => false, 'lunch' => false, 'dinner' => false];

        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days[$d->toDateString()] = array_fill_keys(self::MEALS, ['adults' => 0, 'children' => 0]);
        }

        $reservations = DB::table('pms_reservations')->where('property_id', $propertyId)
            ->whereIn('status', ['confirmed', 'tentative', 'in_house', 'checked_out'])
            ->where('arrival_date', '<=', $to->toDateString())->where('departure_date', '>=', $from->toDateString())
            ->get(['id', 'arrival_date', 'departure_date', 'is_day_use']);
        $nights = DB::table('pms_reservation_nights')->whereIn('reservation_id', $reservations->pluck('id'))->where('status', '!=', 'cancelled')
            ->get(['reservation_id', 'stay_date', 'package_id', 'adults', 'children'])->groupBy('reservation_id');

        foreach ($reservations as $r) {
            $byDate = ($nights[$r->id] ?? collect())->keyBy(fn ($n) => (string) substr((string) $n->stay_date, 0, 10));
            if ($byDate->isEmpty()) {
                continue;
            }
            $arrival = CarbonImmutable::parse($r->arrival_date);
            $departure = CarbonImmutable::parse($r->departure_date);
            $last = $r->is_day_use ? $arrival : $departure;
            for ($d = $arrival; $d->lte($last); $d = $d->addDay()) {
                $key = $d->toDateString();
                if (! isset($days[$key])) {
                    continue;
                }
                $tonight = $byDate[$key] ?? null;
                $lastNight = $byDate[$d->subDay()->toDateString()] ?? null;
                if ($r->is_day_use) {
                    [$take, $pax] = [array_intersect_key($meals($tonight?->package_id), array_filter($options['meals_day_use'])), $tonight];
                } elseif ($d->equalTo($arrival)) {
                    [$take, $pax] = [array_intersect_key($meals($tonight?->package_id), array_filter($options['meals_first_day'])), $tonight];
                } elseif ($d->equalTo($departure)) {
                    [$take, $pax] = [array_intersect_key($meals($lastNight?->package_id), array_filter($options['meals_departure_day'])), $lastNight];
                } else {
                    $take = ['breakfast' => $meals($lastNight?->package_id)['breakfast'], 'lunch' => $meals($tonight?->package_id)['lunch'], 'dinner' => $meals($tonight?->package_id)['dinner']];
                    $pax = $tonight ?? $lastNight;
                }
                if (! $pax) {
                    continue;
                }
                foreach (array_keys(array_filter($take)) as $meal) {
                    $days[$key][$meal]['adults'] += (int) $pax->adults;
                    $days[$key][$meal]['children'] += (int) $pax->children;
                }
            }
        }

        return $days;
    }
}
