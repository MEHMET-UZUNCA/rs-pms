<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\ExchangeRateService;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Rates\Services\RateCalculator;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Web API v1 (otelin web sitesi ayrı projedir; bilgileri buradan okur). Belge: docs/11-web-api.md.
 * Yalnız "Online / Web" işaretli, aktif fiyat kodları ve online satılabilir oda tipleri döner.
 * Fiyatlar PMS'in rezervasyonda kullandığı hesapla (RateCalculator) aynıdır.
 */
class WebController extends Controller
{
    public const MAX_NIGHTS = 30;

    public function roomTypes(): JsonResponse
    {
        $p = app(Property::class);
        $types = DB::table('pms_room_types')->where('property_id', $p->id)->where('is_pseudo', false)->where('is_bookable_online', true)->whereNull('deleted_at')
            ->orderBy('sort')->get();
        // Oda tipinin özellikleri: o tipteki odalarda bulunan özellikler (Sea View, French Bed …)
        $features = DB::table('pms_room_feature_room as rf')->join('pms_room_features as f', 'f.id', '=', 'rf.room_feature_id')
            ->join('pms_rooms as r', 'r.id', '=', 'rf.room_id')->whereIn('r.room_type_id', $types->pluck('id'))
            ->distinct()->get(['r.room_type_id', 'f.code', 'f.name'])->groupBy('room_type_id');

        return response()->json(['data' => $types->map(fn ($t) => [
            'code' => $t->code, 'name' => $t->name, 'description' => $t->description,
            'max_adults' => (int) $t->max_adults, 'max_children' => (int) $t->max_children, 'max_occupancy' => (int) $t->max_occupancy,
            'rooms' => DB::table('pms_rooms')->where('room_type_id', $t->id)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at')->count(),
            'features' => ($features[$t->id] ?? collect())->map(fn ($f) => ['code' => $f->code, 'name' => $f->name])->values(),
        ])->values()]);
    }

    public function rateCodes(): JsonResponse
    {
        return response()->json(['data' => $this->onlineRates()->map(fn (RateCode $r) => [
            'code' => $r->code, 'name' => $r->name, 'board' => $r->package?->code, 'board_name' => $r->package?->name ?? 'No Board',
            'currency' => $r->currency_code, 'sell_from' => $r->valid_from?->toDateString(), 'sell_to' => $r->valid_to?->toDateString(),
            'min_nights' => $r->min_los ?: null, 'max_nights' => $r->max_los ?: null,
            'min_advance_days' => $r->min_advance_days ?: null, 'max_advance_days' => $r->max_advance_days ?: null,
        ])->values()]);
    }

    public function availability(Request $request, AvailabilityService $availability, RateCalculator $calculator, ExchangeRateService $fx): JsonResponse
    {
        $p = app(Property::class);
        $data = $request->validate([
            'arrival' => ['required', 'date_format:Y-m-d'],
            'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'adults' => ['required', 'integer', 'between:1,8'],
            'children' => ['nullable', 'string', 'regex:/^\d{1,2}(,\d{1,2})*$/'],
            'room_type' => ['nullable', 'string', 'max:10'],
            'rate_code' => ['nullable', 'string', 'max:20'],
        ]);
        $arrival = CarbonImmutable::parse($data['arrival']);
        $departure = CarbonImmutable::parse($data['departure']);
        $nights = (int) $arrival->diffInDays($departure);
        $today = $p->current_business_date->toImmutable();
        if ($nights > self::MAX_NIGHTS) {
            return response()->json(['error' => 'validation', 'message' => 'At most '.self::MAX_NIGHTS.' nights per query.'], 422);
        }
        if ($arrival->lt($today)) {
            return response()->json(['error' => 'validation', 'message' => 'Arrival is in the past.'], 422);
        }
        $ages = $data['children'] ?? null ? array_map('intval', explode(',', $data['children'])) : [];
        $bands = $this->childBands($p->id, $ages);
        $adults = (int) $data['adults'];

        $types = DB::table('pms_room_types')->where('property_id', $p->id)->where('is_pseudo', false)->where('is_bookable_online', true)->whereNull('deleted_at')
            ->when($data['room_type'] ?? null, fn ($q, $c) => $q->where('code', $c))->orderBy('sort')->get();
        $lastNight = $departure->subDay();
        $availability->rebuild($p->id, $arrival, $lastNight);
        $rates = $this->onlineRates()->when($data['rate_code'] ?? null, fn ($c, $code) => $c->where('code', $code))
            ->filter(fn (RateCode $r) => $this->sellable($r, $today, $arrival, $nights));

        $result = [];
        foreach ($types as $type) {
            if ($adults + count($ages) > (int) $type->max_occupancy || $adults > max(1, (int) $type->max_adults)) {
                continue;
            }
            $available = (int) DB::table('pms_inventory_daily')->where('property_id', $p->id)->where('room_type_id', $type->id)
                ->whereBetween('date', [$arrival->toDateString(), $lastNight->toDateString()])->min('available');
            $offers = [];
            foreach ($rates as $rate) {
                $nightly = [];
                for ($day = $arrival; $day->lt($departure); $day = $day->addDay()) {
                    $quote = $calculator->quote($rate, (int) $type->id, $day, $adults, $bands);
                    if (! $quote->found()) {
                        continue 2;
                    }
                    $nightly[] = ['date' => $day->toDateString(), 'amount' => $quote->amount];
                }
                $total = round(array_sum(array_column($nightly, 'amount')), 2);
                $offer = ['rate_code' => $rate->code, 'rate_name' => $rate->name, 'board' => $rate->package?->code, 'currency' => $rate->currency_code,
                    'total' => $total, 'nightly' => $nightly];
                if ($rate->currency_code !== $p->base_currency && ($fxRate = $fx->current($p->id, $rate->currency_code))) {
                    $offer['total_base_currency'] = round($total * (float) ($fxRate->ratecode_rate ?? $fxRate->posting_rate), 2);
                    $offer['base_currency'] = $p->base_currency;
                }
                $offers[] = $offer;
            }
            if ($offers) {
                $result[] = ['room_type' => $type->code, 'name' => $type->name, 'available' => max(0, $available), 'rates' => $offers];
            }
        }

        return response()->json([
            'arrival' => $arrival->toDateString(), 'departure' => $departure->toDateString(), 'nights' => $nights,
            'adults' => $adults, 'children' => $ages, 'data' => $result,
        ]);
    }

    /** Aktif, "Online / Web" işaretli fiyat kodları. */
    private function onlineRates()
    {
        return RateCode::where('property_id', app(Property::class)->id)->where('is_active', true)->where('is_public', true)
            ->with('package:id,code,name')->orderBy('sort')->orderBy('code')->get();
    }

    /** Satış dönemi (Startsell/Endsell bugüne göre), en az / en çok gece, erken rezervasyon günleri. */
    private function sellable(RateCode $r, CarbonImmutable $today, CarbonImmutable $arrival, int $nights): bool
    {
        $lead = (int) $today->diffInDays($arrival);

        return (! $r->valid_from || $r->valid_from->lte($today)) && (! $r->valid_to || $r->valid_to->gte($today))
            && (! $r->min_los || $nights >= $r->min_los) && (! $r->max_los || $nights <= $r->max_los)
            && (! $r->min_advance_days || $lead >= $r->min_advance_days) && (! $r->max_advance_days || $lead <= $r->max_advance_days);
    }

    /** Çocuk yaşları → yaş kategorisi sayıları (CA1, CA2 …). Kategorisi olmayan yaş en büyük kategoriye sayılır. */
    private function childBands(int $propertyId, array $ages): array
    {
        $bands = DB::table('pms_child_age_bands')->where('property_id', $propertyId)->orderBy('band')->get();
        $counts = [];
        foreach ($ages as $age) {
            $band = $bands->first(fn ($b) => $age >= $b->age_from && $age <= $b->age_to)?->band ?? $bands->last()?->band ?? 1;
            $counts[$band] = ($counts[$band] ?? 0) + 1;
        }

        return $counts;
    }
}
