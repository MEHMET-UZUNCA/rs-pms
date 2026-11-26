<?php

namespace App\Domain\Housekeeping\Services;

use App\Domain\Core\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Fidelio "House Status" (Ctrl+H): iş gününün anlık durumu.
 *  - Total: toplam oda, satılabilir oda (OOO düşülür).
 *  - Movement: beklenen / gerçekleşen girişler, odası atanmış girişler, beklenen / gerçekleşen çıkışlar, günübirlik, walk-in,
 *    aynı gün rezervasyonlar, oda değişiklikleri, erken çıkışlar, şu anki doluluk — her biri oda / kişi / çocuk.
 *  - Housekeeping: temiz / kirli (dolu / boş), arızalı (OOO), hizmet dışı (OOS), bekleyen odalar (Queue).
 *  - End of Day: bu geceki ortalama oda fiyatı (brüt / net), bu gece boş, blok / grup / bireysel kırılımı, bu gece içeride.
 * Seçenekler: çocuklar kişi sayısına eklensin mi, günübirlikler dahil mi.
 */
class HouseStatusService
{
    /** @return array<string, mixed> */
    public function compute(int $propertyId, bool $childrenInPax = false, bool $includeDayUse = false): array
    {
        $property = Property::query()->findOrFail($propertyId);
        $bd = $property->current_business_date->toDateString();
        $today = now()->toDateString();

        $rooms = DB::table('pms_rooms')->where('property_id', $propertyId)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at');
        $total = (clone $rooms)->count();
        $outages = fn (string $type) => DB::table('pms_room_outages as o')->join('pms_rooms as r', 'r.id', '=', 'o.room_id')
            ->where('r.property_id', $propertyId)->where('o.type', $type)->whereNull('o.released_at')
            ->where('o.date_from', '<=', $bd)->where('o.date_to', '>', $bd);
        $ooo = (clone $outages('ooo'))->count();

        $res = fn () => DB::table('pms_reservations')->where('property_id', $propertyId)
            ->when(! $includeDayUse, fn ($q) => $q->where('is_day_use', false));
        $count = function ($query) use ($childrenInPax) {
            $row = $query->selectRaw('COUNT(*) AS rooms, COALESCE(SUM(adults), 0) AS adults, COALESCE(SUM(children + infants), 0) AS children')->first();

            return ['rooms' => (int) $row->rooms, 'pax' => (int) $row->adults + ($childrenInPax ? (int) $row->children : 0), 'children' => (int) $row->children];
        };
        $expectedArrivals = fn () => $res()->whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', $bd);

        $movement = [
            'expected_arrivals' => $count($expectedArrivals()),
            'actual_arrivals' => $count($res()->whereIn('status', ['in_house', 'checked_out'])->where('check_in_business_date', $bd)),
            'arrival_rooms_assigned' => $count($expectedArrivals()->whereNotNull('room_id')),
            'expected_departures' => $count($res()->where('status', 'in_house')->where('departure_date', '<=', $bd)),
            'actual_departures' => $count($res()->where('status', 'checked_out')->where('check_out_business_date', $bd)),
            'day_rooms' => $count(DB::table('pms_reservations')->where('property_id', $propertyId)->where('is_day_use', true)
                ->whereIn('status', ['confirmed', 'tentative', 'in_house'])->where('arrival_date', $bd)),
            'walk_ins' => $count($res()->where('is_walk_in', true)->where('check_in_business_date', $bd)),
            'same_day_reservations' => $count($res()->whereNotIn('status', ['cancelled'])->where('arrival_date', $bd)->whereDate('created_at', $today)),
            'room_moves' => ['rooms' => DB::table('pms_reservation_room_moves as m')->join('pms_reservations as r', 'r.id', '=', 'm.reservation_id')
                ->where('r.property_id', $propertyId)->where('m.business_date', $bd)->count(), 'pax' => 0, 'children' => 0],
            'early_departures' => $count($res()->where('status', 'checked_out')->where('check_out_business_date', $bd)->where('original_departure_date', '>', $bd)),
        ];
        $current = $count($res()->where('status', 'in_house'));
        $movement['current_occupancy'] = $current + ['pct' => $total - $ooo > 0 ? round($current['rooms'] / ($total - $ooo) * 100, 2) : 0.0];

        // Housekeeping: temiz = clean + inspected; kirli = dirty + pickup
        $hk = fn (array $statuses, ?string $fo) => (clone $rooms)->whereIn('hk_status', $statuses)->when($fo, fn ($q) => $q->where('fo_status', $fo))->count();
        $housekeeping = [];
        foreach (['clean' => ['clean', 'inspected'], 'dirty' => ['dirty', 'pickup']] as $key => $statuses) {
            $housekeeping[$key] = ['total' => $hk($statuses, null), 'occupied' => $hk($statuses, 'occupied'), 'vacant' => $hk($statuses, 'vacant')];
        }
        $housekeeping['total'] = ['occupied' => (clone $rooms)->where('fo_status', 'occupied')->count(), 'vacant' => (clone $rooms)->where('fo_status', 'vacant')->count()];
        foreach (['ooo' => 'out_of_order', 'oos' => 'out_of_service'] as $type => $key) {
            $housekeeping[$key] = ['total' => (clone $outages($type))->count(),
                'occupied' => (clone $outages($type))->where('r.fo_status', 'occupied')->count(), 'vacant' => (clone $outages($type))->where('r.fo_status', 'vacant')->count()];
        }
        $housekeeping['queue'] = $res()->whereNotNull('queued_at')->whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', $bd)->count();

        // Bu gece: bu iş gününün gece satırları (iptal edilmemiş, rezervasyon içeride ya da gelecek)
        $tonight = fn () => DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->where('n.property_id', $propertyId)->where('n.stay_date', $bd)->where('n.status', '!=', 'cancelled')
            ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->when(! $includeDayUse, fn ($q) => $q->where('r.is_day_use', false));
        $tonightCount = function ($query) use ($childrenInPax) {
            $row = $query->selectRaw('COUNT(*) AS rooms, COALESCE(SUM(n.adults), 0) AS adults, COALESCE(SUM(n.children), 0) AS children')->first();

            return ['rooms' => (int) $row->rooms, 'pax' => (int) $row->adults + ($childrenInPax ? (int) $row->children : 0), 'children' => (int) $row->children];
        };
        $paid = (clone $tonight())->where('n.is_complimentary', false)->where('n.is_house_use', false);
        $avg = (clone $paid)->selectRaw('AVG(n.amount) AS gross, AVG(n.net_amount) AS net, AVG(n.amount / (n.adults + 0.0)) AS gross_pp, AVG(n.net_amount / (n.adults + 0.0)) AS net_pp')
            ->where('n.adults', '>', 0)->first();
        $inHouse = $tonightCount($tonight());
        $endOfDay = [
            'adr_gross' => round((float) ($avg->gross ?? 0), 2), 'adr_gross_pp' => round((float) ($avg->gross_pp ?? 0), 2),
            'adr_net' => round((float) ($avg->net ?? 0), 2), 'adr_net_pp' => round((float) ($avg->net_pp ?? 0), 2),
            'available_tonight' => max(0, $total - $ooo - $inHouse['rooms']),
            'allotments' => $tonightCount($tonight()->whereNotNull('r.block_id')),
            'groups' => $tonightCount($tonight()->whereNull('r.block_id')->whereNotNull('r.group_profile_id')),
            'group_and_allotment' => $tonightCount($tonight()->whereNotNull('r.block_id')->whereNotNull('r.group_profile_id')),
            'individuals' => $tonightCount($tonight()->whereNull('r.block_id')->whereNull('r.group_profile_id')),
            'in_house_tonight' => $inHouse,
        ];

        return ['business_date' => $bd, 'total' => ['total' => $total, 'to_sell' => $total - $ooo],
            'movement' => $movement, 'housekeeping' => $housekeeping, 'end_of_day' => $endOfDay,
            'options' => ['children_in_pax' => $childrenInPax, 'include_day_use' => $includeDayUse]];
    }
}
