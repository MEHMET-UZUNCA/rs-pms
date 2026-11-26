<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fidelio "Queue Rooms": misafir geldi ama odası hazır değil — rezervasyon kuyruğa alınır (queued_at). Oda boş ve temiz /
 * kontrol edilmiş olunca "hazır" sayılır; resepsiyon üst çubukta uyarı görür, bekleyen odalar ekranından giriş yapar.
 * Girişte kuyruktan kendiliğinden çıkar.
 */
class QueueService
{
    public const READY_HK = ['clean', 'inspected'];

    public function add(Reservation $reservation): void
    {
        $bd = Property::query()->findOrFail($reservation->property_id)->current_business_date;
        if (! in_array($reservation->status, ['confirmed', 'tentative'], true) || ! $reservation->arrival_date->isSameDay($bd)) {
            throw new ReservationException(__('pms.queue.err_not_arrival'));
        }
        $reservation->update(['queued_at' => now()]);
    }

    public function remove(Reservation $reservation): void
    {
        $reservation->update(['queued_at' => null]);
    }

    /** Kuyruktaki rezervasyonlar ve oda durumları (bekleme süresine göre). */
    public function list(int $propertyId): Collection
    {
        $bd = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();

        return DB::table('pms_reservations as r')->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')->leftJoin('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->where('r.property_id', $propertyId)->whereNotNull('r.queued_at')->whereIn('r.status', ['confirmed', 'tentative'])->where('r.arrival_date', $bd)
            ->orderBy('r.queued_at')
            ->get(['r.id', 'r.confirmation_number', 'r.queued_at', 'r.adults', 'r.children', 'r.departure_date', 'g.display_name', 't.code as room_type',
                'rm.number as room', 'rm.fo_status', 'rm.hk_status', 'rm.service_status'])
            ->map(function ($row) {
                $row->ready = $row->room !== null && $row->fo_status === 'vacant' && in_array($row->hk_status, self::READY_HK, true) && ($row->service_status ?? 'in_service') === 'in_service';
                $row->waiting = (int) now()->diffInMinutes($row->queued_at, true);

                return $row;
            });
    }

    /** Üst çubuktaki uyarı: odası hazır olan kuyruk sayısı / toplam kuyruk. */
    public function counts(int $propertyId): array
    {
        $list = $this->list($propertyId);

        return ['total' => $list->count(), 'ready' => $list->where('ready', true)->count()];
    }
}
