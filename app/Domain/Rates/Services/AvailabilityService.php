<?php

namespace App\Domain\Rates\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Günlük müsaitliği (pms_inventory_daily) kaynak tablolardan yeniden hesaplar:
 * fiziksel oda − arızalı (OOO) − kesin satış − kesin blokta kalan = müsait. OOS ve opsiyonlar bilgi amaçlıdır.
 * Rezervasyon, blok ya da arıza değiştiğinde ilgili aralık için çağrılır; `pms:rebuild-inventory` tüm aralığı yeniden kurar.
 */
class AvailabilityService
{
    private const DEFINITE = ['confirmed', 'in_house'];

    private const TENTATIVE = ['tentative', 'inquiry'];

    public function rebuild(int $propertyId, CarbonInterface $from, CarbonInterface $to, ?int $roomTypeId = null): void
    {
        $roomTypes = DB::table('pms_room_types')->where('property_id', $propertyId)->where('is_pseudo', false)->whereNull('deleted_at')
            ->when($roomTypeId, fn ($q) => $q->where('id', $roomTypeId))->pluck('id');

        DB::transaction(function () use ($propertyId, $from, $to, $roomTypes) {
            for ($day = CarbonImmutable::parse($from); $day->lte($to); $day = $day->addDay()) {
                foreach ($roomTypes as $typeId) {
                    $this->upsertDay($propertyId, (int) $typeId, $day->toDateString());
                }
            }
        });
    }

    private function upsertDay(int $propertyId, int $typeId, string $date): void
    {
        $physical = DB::table('pms_rooms')->where('room_type_id', $typeId)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at')->count();

        $outages = DB::table('pms_room_outages as o')->join('pms_rooms as r', 'r.id', '=', 'o.room_id')
            ->where('r.room_type_id', $typeId)->whereNull('o.released_at')
            ->where('o.date_from', '<=', $date)->where('o.date_to', '>', $date)
            ->selectRaw("SUM(CASE WHEN o.type = 'ooo' THEN 1 ELSE 0 END) AS ooo, SUM(CASE WHEN o.type = 'oos' THEN 1 ELSE 0 END) AS oos")->first();

        $sold = fn (array $statuses, bool $deducts) => DB::table('pms_reservation_nights as n')
            ->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->join('pms_reservation_types as t', 't.id', '=', 'r.reservation_type_id')
            ->where('n.room_type_id', $typeId)->where('n.stay_date', $date)
            ->whereIn('r.status', $statuses)->where('t.deducts_inventory', $deducts)->count();
        $soldDefinite = $sold(self::DEFINITE, true);
        $soldTentative = $sold(self::TENTATIVE, true) + $sold(array_merge(self::DEFINITE, self::TENTATIVE), false);

        $blocks = DB::table('pms_block_inventory as bi')->join('pms_blocks as b', 'b.id', '=', 'bi.block_id')
            ->where('bi.room_type_id', $typeId)->where('bi.date', $date)->whereNull('b.deleted_at')->where('b.status', '<>', 'cancelled')
            ->selectRaw("SUM(CASE WHEN b.status = 'definite' AND b.deducts_inventory = 1 THEN bi.allotted - bi.picked_up - bi.released ELSE 0 END) AS def,
                         SUM(CASE WHEN b.status <> 'definite' OR b.deducts_inventory = 0 THEN bi.allotted - bi.picked_up - bi.released ELSE 0 END) AS tent")
            ->first();
        $blockDefinite = max(0, (int) ($blocks->def ?? 0));
        $blockTentative = max(0, (int) ($blocks->tent ?? 0));

        $overbooking = (int) (DB::table('pms_overbooking_limits')->where('property_id', $propertyId)->where('room_type_id', $typeId)->where('date', $date)->value('limit') ?? 0);
        $ooo = (int) ($outages->ooo ?? 0);

        DB::table('pms_inventory_daily')->updateOrInsert(
            ['property_id' => $propertyId, 'room_type_id' => $typeId, 'date' => $date],
            [
                'physical_rooms' => $physical, 'out_of_order' => $ooo, 'out_of_service' => (int) ($outages->oos ?? 0),
                'sold_definite' => $soldDefinite, 'sold_tentative' => $soldTentative,
                'block_remaining_definite' => $blockDefinite, 'block_remaining_tentative' => $blockTentative,
                'overbooking_limit' => $overbooking,
                'available' => $physical - $ooo - $soldDefinite - $blockDefinite,
                'updated_at' => now(),
            ]
        );
    }
}
