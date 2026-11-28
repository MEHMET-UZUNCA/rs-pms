<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Core\Models\Property;
use App\Domain\Inventory\Models\Room;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Block;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Blok / grup rezervasyonu (Fidelio Block, Elektraweb Grup Rezervasyon).
 *  - Blok başlığı: kod, ad, tip (group / allotment), durum (tentative / definite / cancelled), acenta / şirket, grup profili, tarihler,
 *    fiyat kodu, pazar, kaynak, opsiyon (cutoff) tarihi.
 *  - Oda kotası: oda tipi başına gece gece (pms_block_inventory); başlıkta tek sayı, ayrıntı ekranında gece gece düzenlenir; alınan (picked_up)
 *    rezervasyonlardan sayılır; opsiyon / bırakma gününde alınmamış odalar bırakılır (released).
 *  - Kotadan rezervasyon (pick-up): rooming list'teki her isim için ayrı rezervasyon; isim yoksa grup adıyla açılır, sonra kartta düzeltilir.
 *  - Toplu işler: boş ve temiz odaları otomatik atama, toplu giriş.
 *  - Ana hesap (master): grup adına rezervasyonsuz hesap; üyelerin oda ve pansiyon ücretleri gece kapanışında buraya yönlendirilir.
 */
class BlockService
{
    public const TYPES = ['group', 'allotment'];

    public const STATUSES = ['tentative', 'definite', 'cancelled'];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
        private readonly FolioService $folios,
    ) {}

    /**
     * @param  array<string, mixed>  $data  blok alanları
     * @param  array<int, int>  $rooms  oda tipi id => oda sayısı (her gece)
     */
    public function save(int $propertyId, array $data, array $rooms, ?Block $block, ?int $userId): Block
    {
        return DB::transaction(function () use ($propertyId, $data, $rooms, $block, $userId) {
            $from = CarbonImmutable::parse($data['start_date']);
            $to = CarbonImmutable::parse($data['end_date']);   // çıkış günü (son gece = bir önceki gün)
            if ($to->lte($from)) {
                throw new ReservationException(__('pms.err.block_dates'));
            }
            if ($block && $block->exists) {
                $outside = Reservation::query()->where('block_id', $block->id)->whereNotIn('status', ['cancelled', 'no_show'])
                    ->where(fn ($q) => $q->where('arrival_date', '<', $from->toDateString())->orWhere('departure_date', '>', $to->toDateString()))->exists();
                if ($outside) {
                    throw new ReservationException(__('pms.err.block_outside'));
                }
                $oldFrom = $block->start_date->toImmutable();
                $oldTo = $block->end_date->toImmutable();
                $block->update($data);
            } else {
                $oldFrom = $from;
                $oldTo = $to;
                $block = Block::create($data + ['property_id' => $propertyId, 'created_by' => $userId]);
            }
            if (! $block->group_profile_id && $block->type === 'group') {
                $block->update(['group_profile_id' => DB::table('pms_profiles')->insertGetId([
                    'property_id' => $propertyId, 'type' => 'group', 'company_name' => $block->name, 'display_name' => $block->name,
                    'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ])]);
            }

            // Kota: blok dışındaki geceler silinir, her tip için her gece aynı sayı
            DB::table('pms_block_inventory')->where('block_id', $block->id)
                ->where(fn ($q) => $q->where('date', '<', $from->toDateString())->orWhere('date', '>=', $to->toDateString())->orWhereNotIn('room_type_id', array_keys(array_filter($rooms)) ?: [0]))
                ->delete();
            // Başlıktaki sayı değişmediyse gece gece düzenlenmiş kota korunur; yalnız yeni geceler eklenir
            $before = self::headerCounts($block->id);
            foreach (array_filter($rooms) as $typeId => $count) {
                $changed = ! isset($before[$typeId]) || (int) $before[$typeId] !== (int) $count;
                for ($d = $from; $d->lt($to); $d = $d->addDay()) {
                    $key = ['block_id' => $block->id, 'room_type_id' => $typeId, 'date' => $d->toDateString()];
                    if ($changed || ! DB::table('pms_block_inventory')->where($key)->exists()) {
                        DB::table('pms_block_inventory')->updateOrInsert($key, ['allotted' => (int) $count]);
                    }
                }
            }
            $this->recountPickup($block);
            $this->availability->rebuild($propertyId, $from->min($oldFrom), $to->max($oldTo));

            return $block->fresh();
        });
    }

    /**
     * Başlıktaki "her gece oda" sayısı: oda tipinin gecelerinde en sık geçen kota (eşitlikte büyük olan). Gece gece düzenlenmiş
     * istisnalar başlığı değiştirmez.
     *
     * @return array<int, int>
     */
    public static function headerCounts(int $blockId): array
    {
        return DB::table('pms_block_inventory')->where('block_id', $blockId)->groupBy('room_type_id', 'allotted')
            ->selectRaw('room_type_id, allotted, COUNT(*) AS c')->get()->groupBy('room_type_id')
            ->map(fn ($rows) => (int) $rows->sortBy([['c', 'desc'], ['allotted', 'desc']])->first()->allotted)->all();
    }

    /** Alınan oda sayısı: bloktaki iptal edilmemiş rezervasyonların geceleri. */
    public function recountPickup(Block $block): void
    {
        $picked = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->where('r.block_id', $block->id)->whereNotIn('r.status', ['cancelled', 'no_show'])->where('n.status', '!=', 'cancelled')
            ->groupBy('n.room_type_id', 'n.stay_date')->selectRaw('n.room_type_id, n.stay_date, COUNT(*) AS c')->get()
            ->keyBy(fn ($r) => $r->room_type_id.'|'.substr((string) $r->stay_date, 0, 10));
        foreach (DB::table('pms_block_inventory')->where('block_id', $block->id)->get() as $row) {
            $p = (int) ($picked[$row->room_type_id.'|'.substr((string) $row->date, 0, 10)]->c ?? 0);
            DB::table('pms_block_inventory')->where('id', $row->id)->update(['picked_up' => $p, 'released' => max(0, min((int) $row->released, (int) $row->allotted - $p))]);
        }
    }

    /**
     * Oda tipi başına kota / alınan / kalan (blok boyunca en düşük kalan).
     *
     * @return Collection<int, object{room_type_id: int, code: string, name: string, allotted: int, picked_up: int, released: int, remaining: int}>
     */
    public function summary(Block $block): Collection
    {
        return DB::table('pms_block_inventory as bi')->join('pms_room_types as t', 't.id', '=', 'bi.room_type_id')->where('bi.block_id', $block->id)
            ->groupBy('bi.room_type_id', 't.code', 't.name', 't.sort')->orderBy('t.sort')
            ->selectRaw('bi.room_type_id, t.code, t.name, MAX(bi.allotted) AS allotted, MAX(bi.picked_up) AS picked_up, MAX(bi.released) AS released, MIN(bi.allotted - bi.picked_up - bi.released) AS remaining')
            ->get();
    }

    /**
     * Kotadan rezervasyon açar. $names boşsa $count kadar grup adıyla açılır.
     *
     * @param  list<string>  $names  "Soyad, Ad" ya da "Ad Soyad" satırları
     * @param  array<string, mixed>  $extra  adults, reservation_type_id, arrival_date, departure_date
     * @return list<Reservation>
     */
    public function pickUp(Block $block, int $roomTypeId, int $count, array $names, array $extra, ?int $userId): array
    {
        if ($block->status === 'cancelled') {
            throw new ReservationException(__('pms.err.block_cancelled'));
        }
        $names = array_values(array_filter(array_map('trim', $names), fn ($n) => $n !== ''));
        $count = max($count, count($names));
        if ($count < 1 || $count > 200) {
            throw new ReservationException(__('pms.err.block_count'));
        }
        $arrival = CarbonImmutable::parse($extra['arrival_date'] ?? $block->start_date);
        $departure = CarbonImmutable::parse($extra['departure_date'] ?? $block->end_date);
        if ($arrival->lt($block->start_date->toImmutable()) || $departure->gt($block->end_date->toImmutable()) || $departure->lte($arrival)) {
            throw new ReservationException(__('pms.err.block_range'));
        }
        $remaining = (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->where('room_type_id', $roomTypeId)
            ->whereBetween('date', [$arrival->toDateString(), $departure->subDay()->toDateString()])->selectRaw('MIN(allotted - picked_up - released) AS r')->value('r');
        if ($remaining < $count) {
            throw new ReservationException(__('pms.err.block_short', ['left' => $remaining]));
        }
        if (! $block->rate_code_id || ! $block->market_segment_id || ! $block->source_code_id) {
            throw new ReservationException(__('pms.err.block_codes'));
        }

        return DB::transaction(function () use ($block, $roomTypeId, $count, $names, $extra, $arrival, $departure, $userId) {
            $created = [];
            for ($i = 0; $i < $count; $i++) {
                $profileId = $this->guestProfile($block, $names[$i] ?? null, $userId);
                $created[] = $this->reservations->create([
                    'property_id' => $block->property_id, 'profile_id' => $profileId, 'room_type_id' => $roomTypeId, 'rate_code_id' => $block->rate_code_id,
                    'arrival_date' => $arrival->toDateString(), 'departure_date' => $departure->toDateString(), 'adults' => (int) ($extra['adults'] ?? 2),
                    'reservation_type_id' => $extra['reservation_type_id'], 'market_segment_id' => $block->market_segment_id, 'source_code_id' => $block->source_code_id,
                    'block_id' => $block->id, 'group_profile_id' => $block->group_profile_id,
                    'agent_profile_id' => $this->profileType($block->profile_id) === 'travel_agent' ? $block->profile_id : null,
                    'company_profile_id' => $this->profileType($block->profile_id) === 'company' ? $block->profile_id : null,
                    'status' => $block->status === 'definite' ? 'confirmed' : 'tentative',
                ], $userId);
            }
            $this->recountPickup($block);
            $this->availability->rebuild($block->property_id, $arrival, $departure, $roomTypeId);

            return $created;
        });
    }

    /**
     * Seçili rezervasyonlara oda tipinden boş, temiz / kontrol edilmiş ve arızasız odaları sırayla atar.
     *
     * @param  list<int>  $ids
     * @return array{assigned: int, skipped: list<string>}
     */
    public function autoAssign(Block $block, array $ids, ?int $userId): array
    {
        $assigned = 0;
        $skipped = [];
        foreach ($this->members($block, $ids)->whereNull('room_id')->whereIn('status', ['confirmed', 'tentative']) as $reservation) {
            $candidates = Room::query()->where('property_id', $block->property_id)->where('room_type_id', $reservation->room_type_id)
                ->where('is_active', true)->where('is_pseudo', false)->orderByRaw("CASE WHEN fo_status = 'vacant' AND hk_status IN ('clean', 'inspected') THEN 0 ELSE 1 END")
                ->orderBy('number')->get();
            $done = false;
            foreach ($candidates as $room) {
                try {
                    $this->reservations->assignRoom($reservation, $room, $userId);
                    $assigned++;
                    $done = true;
                    break;
                } catch (ReservationException) {
                    continue;   // dolu, çakışan ya da arızalı oda: sıradaki
                }
            }
            if (! $done) {
                $skipped[] = $reservation->confirmation_number;
            }
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    /**
     * Gece gece kota (Fidelio Block → Grid): $grid[oda tipi id][Y-m-d] = oda sayısı. Alınan oda sayısının altına inilemez;
     * bırakılmış (released) geceye yeni sayı girilirse bırakma sıfırlanır.
     *
     * @param  array<int|string, array<string, int|string|null>>  $grid
     */
    public function saveInventory(Block $block, array $grid): int
    {
        return DB::transaction(function () use ($block, $grid) {
            $this->recountPickup($block);
            $rows = DB::table('pms_block_inventory')->where('block_id', $block->id)->get()->keyBy(fn ($r) => $r->room_type_id.'|'.substr((string) $r->date, 0, 10));
            $changed = 0;
            foreach ($grid as $typeId => $days) {
                foreach ((array) $days as $date => $value) {
                    $row = $rows[$typeId.'|'.$date] ?? null;
                    if (! $row || $value === null || $value === '' || (int) $value === (int) $row->allotted) {
                        continue;
                    }
                    $value = max(0, (int) $value);
                    if ($value < (int) $row->picked_up) {
                        throw new ReservationException(__('pms.err.block_below_pickup', ['date' => CarbonImmutable::parse($date)->format('d.m.Y'), 'picked' => $row->picked_up]));
                    }
                    DB::table('pms_block_inventory')->where('id', $row->id)->update(['allotted' => $value, 'released' => 0]);
                    $changed++;
                }
            }
            if ($changed) {
                $this->availability->rebuild($block->property_id, $block->start_date->toImmutable(), $block->end_date->toImmutable());
            }

            return $changed;
        });
    }

    /**
     * Opsiyon / bırakma (Fidelio cutoff, release days): gece kapanışında yeni iş gününe göre çalışır.
     *  - Opsiyon (cutoff) tarihi geçmiş bloklarda bugünden sonraki bütün gecelerin alınmamış odaları,
     *  - "bırakma günü" girilmiş bloklarda geceye o kadar gün kalmışsa o gecenin alınmamış odaları
     * bırakılır (released): bloktan düşer, otelin genel müsaitliğine döner. Alınmış odalar etkilenmez.
     *
     * @return array{blocks_released: int, rooms_released: int}
     */
    public function releaseDue(int $propertyId, string $businessDate): array
    {
        $bd = CarbonImmutable::parse($businessDate);
        $blocks = Block::query()->where('property_id', $propertyId)->where('status', '<>', 'cancelled')->where('end_date', '>', $businessDate)
            ->where(fn ($q) => $q->where('cutoff_date', '<', $businessDate)->orWhere('release_days', '>', 0))->get();
        $count = 0;
        $rooms = 0;
        foreach ($blocks as $block) {
            $this->recountPickup($block);
            $until = $block->cutoff_date && $block->cutoff_date->toImmutable()->lt($bd) ? $block->end_date->toImmutable() : $bd->addDays((int) $block->release_days);
            $released = (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->where('date', '>=', $businessDate)->where('date', '<=', $until->toDateString())
                ->whereRaw('allotted - picked_up - released > 0')->sum(DB::raw('allotted - picked_up - released'));
            if ($released > 0) {
                DB::table('pms_block_inventory')->where('block_id', $block->id)->where('date', '>=', $businessDate)->where('date', '<=', $until->toDateString())
                    ->update(['released' => DB::raw('allotted - picked_up')]);
                $this->availability->rebuild($propertyId, $bd, $block->end_date->toImmutable());
                $count++;
                $rooms += $released;
            }
        }

        return ['blocks_released' => $count, 'rooms_released' => $rooms];
    }

    /**
     * Toplu çıkış: bugün (ya da daha önce) çıkacak, folyo bakiyeleri sıfır seçili üyeler. Bakiyesi olan atlanır ve bildirilir.
     *
     * @param  list<int>  $ids
     * @return array{checked_out: int, errors: list<string>}
     */
    public function checkOutMany(Block $block, array $ids, ?int $userId): array
    {
        $bd = Property::query()->findOrFail($block->property_id)->current_business_date->toDateString();
        $count = 0;
        $errors = [];
        foreach ($this->members($block, $ids)->where('status', 'in_house') as $reservation) {
            if ($reservation->departure_date->toDateString() > $bd) {
                $errors[] = $reservation->confirmation_number.': '.__('pms.err.block_not_due');

                continue;
            }
            try {
                $this->reservations->checkOut($reservation, $userId);
                $count++;
            } catch (ReservationException $e) {
                $errors[] = $reservation->confirmation_number.': '.$e->getMessage();
            }
        }

        return ['checked_out' => $count, 'errors' => $errors];
    }

    /**
     * Toplu giriş: bugün gelen, odası atanmış seçili rezervasyonlar.
     *
     * @param  list<int>  $ids
     * @return array{checked_in: int, errors: list<string>}
     */
    public function checkInMany(Block $block, array $ids, ?int $userId): array
    {
        $count = 0;
        $errors = [];
        foreach ($this->members($block, $ids)->whereIn('status', ['confirmed', 'tentative']) as $reservation) {
            try {
                $this->reservations->checkIn($reservation, $userId);
                $count++;
            } catch (ReservationException $e) {
                $errors[] = $reservation->confirmation_number.': '.$e->getMessage();
            }
        }

        return ['checked_in' => $count, 'errors' => $errors];
    }

    /**
     * Grup ana hesabı (master): yoksa açılır; seçili üyelerin oda ve pansiyon ücretleri gece kapanışında bu hesaba yazılır.
     *
     * @param  list<int>  $ids
     */
    public function routeToMaster(Block $block, array $ids, ?int $userId): Folio
    {
        return DB::transaction(function () use ($block, $ids, $userId) {
            $master = $this->master($block) ?? $this->folios->saveAccount($block->property_id, [
                'type' => 'master', 'name' => mb_substr($block->name, 0, 100), 'payee_profile_id' => $block->profile_id,
                'valid_from' => $block->start_date->toDateString(), 'valid_until' => $block->end_date->toDateString(),
                'notes' => "Blok {$block->code} ana hesabı",
            ], null, $userId);
            if (! $block->master_folio_id) {
                $block->update(['master_folio_id' => $master->id]);
            }
            foreach ($this->members($block, $ids)->whereNotIn('status', ['cancelled', 'no_show', 'checked_out']) as $reservation) {
                DB::table('pms_routing_rules')->updateOrInsert(
                    ['source_reservation_id' => $reservation->id, 'target_type' => 'folio', 'target_folio_id' => $master->id],
                    ['property_id' => $block->property_id, 'transaction_code_id' => null, 'transaction_code_group_id' => null, 'is_active' => true,
                        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $master;
        });
    }

    /** @param list<int> $ids */
    public function removeRouting(Block $block, array $ids): int
    {
        $master = $this->master($block);

        return $master ? DB::table('pms_routing_rules')->whereIn('source_reservation_id', $this->members($block, $ids)->pluck('id'))
            ->where('target_folio_id', $master->id)->delete() : 0;
    }

    public function master(Block $block): ?Folio
    {
        return $block->master_folio_id ? Folio::query()->find($block->master_folio_id) : null;
    }

    /** @return Collection<int, Reservation> */
    private function members(Block $block, array $ids): Collection
    {
        return Reservation::query()->where('block_id', $block->id)->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function guestProfile(Block $block, ?string $name, ?int $userId): int
    {
        if ($name === null) {
            $last = $block->name;
            $first = null;
        } elseif (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
        } else {
            $parts = preg_split('/\s+/', $name);
            $last = array_pop($parts);
            $first = implode(' ', $parts) ?: null;
        }
        $last = mb_substr($last, 0, 100);

        return DB::table('pms_profiles')->insertGetId([
            'property_id' => $block->property_id, 'type' => 'individual', 'last_name' => $last, 'first_name' => $first,
            'display_name' => $first ? "{$last}, {$first}" : $last, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function profileType(?int $id): ?string
    {
        return $id ? DB::table('pms_profiles')->where('id', $id)->value('type') : null;
    }

    public static function businessDate(int $propertyId): CarbonImmutable
    {
        return Property::query()->findOrFail($propertyId)->current_business_date->toImmutable();
    }
}
