<?php

namespace App\Domain\FrontOffice\Services;

use App\Domain\Core\Models\Property;
use App\Domain\Inventory\Models\Room;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Misafir istekleri ve şikâyetler (pms_traces): oda / misafir, sorumlu departman, son saat, öncelik, durum ve işlem geçmişi.
 * Son saati geçen açık iş "gecikmiş" sayılır; üst çubukta sayısı görünür (yöneticiler ve ilgili personel görür).
 * Fidelio Messages / Locator değildir; ayrı operasyon modülüdür.
 */
class GuestRequestService
{
    public const DEPARTMENTS = ['front_office', 'housekeeping', 'technical', 'fnb', 'concierge', 'spa', 'guest_relations', 'security'];

    public const KINDS = ['request', 'complaint'];

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];

    /** @param array{room?: ?string, reservation_id?: ?int, kind: string, department: string, priority?: string, due_at: string, message: string, guest_name?: ?string} $data */
    public function create(int $propertyId, array $data, ?int $userId): int
    {
        $room = ! empty($data['room']) ? Room::query()->where('property_id', $propertyId)->where('number', trim((string) $data['room']))->first() : null;
        if (! empty($data['room']) && ! $room) {
            throw new RuntimeException(__('pms.req.no_room', ['room' => $data['room']]));
        }
        $reservation = ! empty($data['reservation_id'])
            ? Reservation::query()->where('property_id', $propertyId)->find($data['reservation_id'])
            : ($room ? Reservation::query()->where('room_id', $room->id)->where('status', 'in_house')->first() : null);
        $room ??= $reservation?->room;
        $due = CarbonImmutable::parse($data['due_at']);
        $target = $reservation ? [Reservation::class, $reservation->id] : ($room ? [Room::class, $room->id] : [Property::class, $propertyId]);

        return DB::transaction(function () use ($propertyId, $data, $userId, $reservation, $room, $due, $target) {
            $id = DB::table('pms_traces')->insertGetId([
                'property_id' => $propertyId, 'traceable_type' => $target[0], 'traceable_id' => $target[1],
                'department' => $data['department'], 'kind' => $data['kind'], 'priority' => $data['priority'] ?? 'normal', 'room_id' => $room?->id,
                'guest_name' => mb_substr((string) (($data['guest_name'] ?? null) ?: $reservation?->profile?->display_name), 0, 150) ?: null,
                'trace_date' => $due->toDateString(), 'trace_time' => $due->format('H:i:s'), 'message' => $data['message'], 'status' => 'open',
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('pms_trace_logs')->insert(['trace_id' => $id, 'user_id' => $userId, 'status' => 'open', 'note' => null, 'created_at' => now()]);

            return $id;
        });
    }

    public function changeStatus(int $traceId, string $status, ?string $note, ?int $userId): void
    {
        $trace = DB::table('pms_traces')->where('id', $traceId)->first();
        if (! $trace || ! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('?');
        }
        if (in_array($trace->status, ['done', 'cancelled'], true) && $status !== 'open') {
            throw new RuntimeException(__('pms.req.closed'));
        }
        DB::transaction(function () use ($trace, $status, $note, $userId) {
            $done = in_array($status, ['done', 'cancelled'], true);
            DB::table('pms_traces')->where('id', $trace->id)->update(['status' => $status, 'is_resolved' => $done,
                'resolved_by' => $done ? $userId : null, 'resolved_at' => $done ? now() : null, 'updated_at' => now()]);
            DB::table('pms_trace_logs')->insert(['trace_id' => $trace->id, 'user_id' => $userId, 'status' => $status, 'note' => $note ?: null, 'created_at' => now()]);
        });
    }

    public function note(int $traceId, string $note, ?int $userId): void
    {
        DB::table('pms_trace_logs')->insert(['trace_id' => $traceId, 'user_id' => $userId, 'status' => null, 'note' => $note, 'created_at' => now()]);
        DB::table('pms_traces')->where('id', $traceId)->update(['updated_at' => now()]);
    }

    /** Son saati geçmiş açık / işlemdeki işler. */
    public function overdueCount(int $propertyId): int
    {
        $now = now();

        return DB::table('pms_traces')->where('property_id', $propertyId)->whereIn('status', ['open', 'in_progress'])
            ->where(fn ($q) => $q->where('trace_date', '<', $now->toDateString())
                ->orWhere(fn ($w) => $w->where('trace_date', $now->toDateString())->where('trace_time', '<', $now->format('H:i:s'))))
            ->count();
    }

    public static function isOverdue(object $trace): bool
    {
        return in_array($trace->status, ['open', 'in_progress'], true)
            && CarbonImmutable::parse(substr((string) $trace->trace_date, 0, 10).' '.($trace->trace_time ?? '23:59:59'))->lt(now());
    }
}
