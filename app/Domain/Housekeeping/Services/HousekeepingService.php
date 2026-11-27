<?php

namespace App\Domain\Housekeeping\Services;

use App\Domain\Core\Models\Property;
use App\Domain\Housekeeping\Exceptions\HousekeepingException;
use App\Domain\Rates\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kat hizmetleri: oda temizlik durumu (Fidelio kodlarıyla), işaretler (DND, Sleep Out …), arızalı (OOO) / hizmet dışı (OOS) dönemler.
 * Her değişiklik pms_room_status_logs'a yazılır. OOO, o dönemde odası atanmış rezervasyon ya da içerideki misafir varken girilemez.
 */
class HousekeepingService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @param array<int> $roomIds */
    public function setStatus(int $propertyId, array $roomIds, string $code, ?int $userId): int
    {
        $status = DB::table('pms_hk_status_codes')->where('property_id', $propertyId)->where('code', $code)->where('is_active', true)->first()
            ?? throw new HousekeepingException(__('pms.err.hk_code', ['code' => $code]));

        return DB::transaction(function () use ($propertyId, $roomIds, $status, $userId) {
            $count = 0;
            foreach (DB::table('pms_rooms')->where('property_id', $propertyId)->whereIn('id', $roomIds)->lockForUpdate()->get() as $room) {
                [$field, $value] = $status->kind === 'flag' ? ['hk_flag', $status->code] : ['hk_status', $status->maps_to];
                if ($room->{$field} === $value) {
                    continue;
                }
                DB::table('pms_rooms')->where('id', $room->id)->update([$field => $value, 'hk_status_changed_at' => now(), 'updated_at' => now()]);
                $this->log($propertyId, $room->id, $field, $room->{$field}, $value, $userId);
                $count++;
            }

            return $count;
        });
    }

    /** @param array<int> $roomIds */
    public function clearFlag(int $propertyId, array $roomIds, ?int $userId): int
    {
        $count = 0;
        foreach (DB::table('pms_rooms')->where('property_id', $propertyId)->whereIn('id', $roomIds)->whereNotNull('hk_flag')->get() as $room) {
            DB::table('pms_rooms')->where('id', $room->id)->update(['hk_flag' => null, 'updated_at' => now()]);
            $this->log($propertyId, $room->id, 'hk_flag', $room->hk_flag, '', $userId);
            $count++;
        }

        return $count;
    }

    /** OOO / OOS: [from, to) dönemi. OOO müsaitlikten düşer; içerideki misafirli ya da o dönemde odası atanmış rezervasyonlu odaya girilmez. */
    public function putOutOfOrder(int $propertyId, int $roomId, string $type, string $from, string $to, int $reasonCodeId, ?string $note, ?int $userId): int
    {
        $type = strtoupper($type);
        if (! in_array($type, ['OOO', 'OOS'], true)) {
            throw new HousekeepingException(__('pms.err.outage_type'));
        }
        [$f, $t] = [CarbonImmutable::parse($from), CarbonImmutable::parse($to)];
        if ($t->lte($f)) {
            throw new HousekeepingException(__('pms.err.end_after_start'));
        }

        return DB::transaction(function () use ($propertyId, $roomId, $type, $f, $t, $reasonCodeId, $note, $userId) {
            $room = DB::table('pms_rooms')->where('property_id', $propertyId)->where('id', $roomId)->lockForUpdate()->first()
                ?? throw new HousekeepingException(__('pms.err.room_not_found'));
            if ($type === 'OOO') {
                $clash = DB::table('pms_reservations')->where('room_id', $roomId)->whereIn('status', ['confirmed', 'tentative', 'in_house'])
                    ->where('arrival_date', '<', $t->toDateString())->where('departure_date', '>', $f->toDateString())->value('confirmation_number');
                if ($clash) {
                    throw new HousekeepingException(__('pms.err.outage_reserved', ['room' => $room->number, 'no' => $clash]));
                }
            }
            $overlap = DB::table('pms_room_outages')->where('room_id', $roomId)->whereNull('released_at')
                ->where('date_from', '<', $t->toDateString())->where('date_to', '>', $f->toDateString())->exists();
            if ($overlap) {
                throw new HousekeepingException(__('pms.err.outage_exists', ['room' => $room->number]));
            }
            $id = DB::table('pms_room_outages')->insertGetId([
                'property_id' => $propertyId, 'room_id' => $roomId, 'type' => $type, 'date_from' => $f->toDateString(), 'date_to' => $t->toDateString(),
                'reason_code_id' => $reasonCodeId, 'note' => $note, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncServiceStatus($propertyId, $roomId, $userId);
            $this->availability->rebuild($propertyId, $f, $t->subDay(), (int) $room->room_type_id);

            return $id;
        });
    }

    public function release(int $propertyId, int $outageId, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $outageId, $userId) {
            $outage = DB::table('pms_room_outages')->where('property_id', $propertyId)->where('id', $outageId)->whereNull('released_at')->first()
                ?? throw new HousekeepingException(__('pms.err.outage_not_found'));
            DB::table('pms_room_outages')->where('id', $outageId)->update(['released_at' => now(), 'released_by' => $userId, 'updated_at' => now()]);
            $room = DB::table('pms_rooms')->find($outage->room_id);
            if ($outage->return_hk_status && $room->hk_status !== $outage->return_hk_status) {
                DB::table('pms_rooms')->where('id', $room->id)->update(['hk_status' => $outage->return_hk_status]);
                $this->log($propertyId, $room->id, 'hk_status', $room->hk_status, $outage->return_hk_status, $userId);
            }
            $this->syncServiceStatus($propertyId, (int) $outage->room_id, $userId);
            $this->availability->rebuild($propertyId, CarbonImmutable::parse($outage->date_from), CarbonImmutable::parse($outage->date_to)->subDay(), (int) $room->room_type_id);
        });
    }

    /** Odanın bugünkü hizmet durumu açık OOO/OOS kaydına göre. */
    public function syncServiceStatus(int $propertyId, int $roomId, ?int $userId = null): void
    {
        $today = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();
        $type = DB::table('pms_room_outages')->where('room_id', $roomId)->whereNull('released_at')
            ->where('date_from', '<=', $today)->where('date_to', '>', $today)->value('type');
        $status = match (strtoupper((string) $type)) {
            'OOO' => 'out_of_order', 'OOS' => 'out_of_service', default => 'in_service'
        };
        $room = DB::table('pms_rooms')->find($roomId);
        if ($room->service_status !== $status) {
            DB::table('pms_rooms')->where('id', $roomId)->update(['service_status' => $status, 'updated_at' => now()]);
            $this->log($propertyId, $roomId, 'service_status', $room->service_status, $status, $userId);
        }
    }

    private function log(int $propertyId, int $roomId, string $field, ?string $from, string $to, ?int $userId): void
    {
        DB::table('pms_room_status_logs')->insert(['property_id' => $propertyId, 'room_id' => $roomId, 'field' => $field, 'from_value' => $from,
            'to_value' => $to, 'source' => 'manual', 'user_id' => $userId, 'created_at' => now()]);
    }
}
