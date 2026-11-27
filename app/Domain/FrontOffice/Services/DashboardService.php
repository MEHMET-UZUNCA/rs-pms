<?php

namespace App\Domain\FrontOffice\Services;

use App\Domain\Core\Models\Property;
use App\Domain\Housekeeping\Services\HouseStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ön büro kontrol paneli (ana sayfa): iş gününün özeti (House Status), son kapanan günün geliri (gece kapanışı istatistiği),
 * önümüzdeki günlerin doluluk tahmini ve bugün gelen / giden misafirler.
 */
class DashboardService
{
    public const FORECAST_DAYS = 14;

    public function __construct(private readonly HouseStatusService $houseStatus) {}

    /** @return array<string, mixed> */
    public function compute(int $propertyId): array
    {
        $property = Property::query()->findOrFail($propertyId);
        $bd = $property->current_business_date->toImmutable();
        $status = $this->houseStatus->compute($propertyId);

        $lastClosed = DB::table('pms_daily_statistics')->where('property_id', $propertyId)->where('dimension', 'total')->where('dimension_key', '')
            ->where('business_date', '<', $bd->toDateString())->orderByDesc('business_date')->first();

        return [
            'business_date' => $bd,
            'status' => $status,
            'last_closed' => $lastClosed,
            'forecast' => $this->forecast($propertyId, $bd, (int) $status['total']['total']),
            'arrivals' => $this->list($propertyId, fn ($q) => $q->whereIn('r.status', ['confirmed', 'tentative'])->where('r.arrival_date', $bd->toDateString()), 'r.eta'),
            'departures' => $this->list($propertyId, fn ($q) => $q->where('r.status', 'in_house')->where('r.departure_date', '<=', $bd->toDateString()), 'r.etd'),
        ];
    }

    /**
     * Gün gün satılan oda (kesin + opsiyon), arızalı oda ve doluluk yüzdesi.
     *
     * @return list<array{date: CarbonImmutable, sold: int, tentative: int, ooo: int, sellable: int, pct: float}>
     */
    public function forecast(int $propertyId, CarbonImmutable $from, int $totalRooms): array
    {
        $to = $from->addDays(self::FORECAST_DAYS - 1);
        $nights = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->where('n.property_id', $propertyId)->where('n.status', '!=', 'cancelled')->where('r.is_day_use', false)
            ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->whereBetween('n.stay_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('n.stay_date')->selectRaw("n.stay_date AS d, SUM(CASE WHEN r.status = 'tentative' THEN 0 ELSE 1 END) AS sold, SUM(CASE WHEN r.status = 'tentative' THEN 1 ELSE 0 END) AS tentative")
            ->get()->keyBy(fn ($row) => substr((string) $row->d, 0, 10));
        $outages = DB::table('pms_room_outages as o')->join('pms_rooms as rm', 'rm.id', '=', 'o.room_id')->where('rm.property_id', $propertyId)
            ->where('o.type', 'ooo')->whereNull('o.released_at')->where('o.date_from', '<=', $to->toDateString())->where('o.date_to', '>', $from->toDateString())
            ->get(['o.date_from', 'o.date_to']);

        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $ooo = $outages->filter(fn ($o) => substr((string) $o->date_from, 0, 10) <= $key && substr((string) $o->date_to, 0, 10) > $key)->count();
            $sold = (int) ($nights[$key]->sold ?? 0);
            $tentative = (int) ($nights[$key]->tentative ?? 0);
            $sellable = max(0, $totalRooms - $ooo);
            $days[] = ['date' => $d, 'sold' => $sold, 'tentative' => $tentative, 'ooo' => $ooo, 'sellable' => $sellable,
                'pct' => $sellable > 0 ? round(($sold + $tentative) / $sellable * 100, 1) : 0.0];
        }

        return $days;
    }

    private function list(int $propertyId, callable $filter, string $timeColumn): Collection
    {
        $query = DB::table('pms_reservations as r')->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')->leftJoin('pms_room_types as rt', 'rt.id', '=', 'r.room_type_id')
            ->leftJoin('pms_vip_levels as v', 'v.id', '=', 'r.vip_level_id')->where('r.property_id', $propertyId);
        $filter($query);

        return $query->orderByRaw($timeColumn.' IS NULL')->orderBy($timeColumn)->orderBy('g.display_name')->limit(12)
            ->get(['r.id', 'r.confirmation_number', 'r.adults', 'r.children', 'r.eta', 'r.etd', 'r.departure_date', 'r.queued_at', 'g.display_name', 'g.status as profile_status',
                'rm.number as room', 'rm.hk_status', 'rt.code as room_type', 'v.code as vip']);
    }
}
