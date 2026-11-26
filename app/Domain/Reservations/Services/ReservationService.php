<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Core\Services\TaxCalculator;
use App\Domain\Inventory\Models\Room;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Rates\Services\RateCalculator;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Domain\Reservations\Models\ReservationNight;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rezervasyon yaşam döngüsü: açma, tarih değiştirme, iptal, oda atama, giriş, çıkış.
 * Gece satırları fiyat hesaplamasıyla doldurulur; elle girilen gece fiyatı (override) tarih değişikliğinde korunur.
 * Her değişiklikte müsaitlik ilgili aralık için yeniden hesaplanır ve durum geçmişi yazılır.
 */
class ReservationService
{
    public function __construct(
        private readonly RateCalculator $rates,
        private readonly TaxCalculator $taxes,
        private readonly AvailabilityService $availability,
        private readonly NumberSequenceService $numbers,
    ) {}

    /**
     * @param  array<string,mixed>  $data  property_id, profile_id, room_type_id, rate_code_id, arrival_date, departure_date, adults,
     *                                     reservation_type_id, market_segment_id, source_code_id + isteğe bağlı diğer rezervasyon alanları,
     *                                     child_bands [1=>n,2=>n,3=>n], extra_beds, manual_amount (tüm geceler için elle fiyat)
     */
    public function create(array $data, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($data, $userId) {
            $arrival = CarbonImmutable::parse($data['arrival_date']);
            $departure = CarbonImmutable::parse($data['departure_date']);
            if ($departure->lte($arrival) && empty($data['is_day_use'])) {
                throw new ReservationException('Çıkış tarihi girişten sonra olmalı.');
            }
            $bands = $data['child_bands'] ?? [];
            $manual = $data['manual_amount'] ?? null;
            unset($data['child_bands'], $data['manual_amount']);

            $rateCode = RateCode::query()->findOrFail($data['rate_code_id']);
            $nights = max(1, $arrival->diffInDays($departure));
            $reservation = Reservation::create(array_merge([
                'confirmation_number' => $this->numbers->next((int) $data['property_id'], 'confirmation'),
                'status' => 'confirmed',
                'original_departure_date' => $departure->toDateString(),
                'nights' => $nights,
                'charge_room_type_id' => $data['room_type_id'],
                'currency_code' => $rateCode->currency_code,
                'children' => (int) array_sum($bands),
                'child_band_1' => (int) ($bands[1] ?? 0), 'child_band_2' => (int) ($bands[2] ?? 0), 'child_band_3' => (int) ($bands[3] ?? 0),
                'sale_date' => Property::query()->find($data['property_id'])->current_business_date,
                'created_by' => $userId, 'updated_by' => $userId,
            ], $data));

            $this->buildNights($reservation, $bands, (int) ($data['extra_beds'] ?? 0), $manual);
            $this->log($reservation, null, $reservation->status, $userId);
            $this->refreshAvailability($reservation, $arrival, $departure);

            return $reservation->fresh(['nightRows']);
        });
    }

    /** Tarih değişikliği: kalan gecelerin elle fiyatı korunur, yeni geceler fiyat kodundan hesaplanır. */
    public function changeDates(Reservation $reservation, string $arrival, string $departure, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $arrival, $departure, $userId) {
            $this->assertActive($reservation);
            $oldFrom = $reservation->arrival_date;
            $oldTo = $reservation->departure_date;
            $from = CarbonImmutable::parse($arrival);
            $to = CarbonImmutable::parse($departure);
            if ($reservation->status === 'in_house' && ! $from->isSameDay($reservation->arrival_date)) {
                throw new ReservationException('İçerideki misafirin giriş tarihi değiştirilemez.');
            }
            if ($to->lte($from)) {
                throw new ReservationException('Çıkış tarihi girişten sonra olmalı.');
            }

            $kept = $reservation->nightRows()->where('is_overridden', true)->get()->keyBy(fn ($n) => $n->stay_date->toDateString());
            $reservation->nightRows()->where('status', 'expected')->delete();
            $reservation->update(['arrival_date' => $from->toDateString(), 'departure_date' => $to->toDateString(), 'nights' => $from->diffInDays($to), 'updated_by' => $userId]);
            $bands = [1 => $reservation->child_band_1, 2 => $reservation->child_band_2, 3 => $reservation->child_band_3];
            $this->buildNights($reservation->fresh(), $bands, (int) $reservation->extra_beds, null, $kept->map(fn ($n) => (float) $n->amount)->all());

            $this->refreshAvailability($reservation, $from->min($oldFrom), $to->max($oldTo));

            return $reservation->fresh(['nightRows']);
        });
    }

    /**
     * Rezervasyon kartından düzenleme. Tarih değişirse geceler yeniden kurulur (elle fiyat korunur); oda tipi, fiyat kodu, kişi sayısı ya da
     * elle fiyat değişirse gelmemiş geceler yeniden fiyatlanır; oda değişirse oda atama kuralları (çakışma, arıza) uygulanır.
     *
     * @param  array<string,mixed>  $data  rezervasyon alanları + isteğe bağlı child_bands [1=>n,…], manual_amount, room_id
     */
    public function update(Reservation $reservation, array $data, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $userId) {
            $this->assertActive($reservation);
            $bands = $data['child_bands'] ?? [1 => $reservation->child_band_1, 2 => $reservation->child_band_2, 3 => $reservation->child_band_3];
            $manual = array_key_exists('manual_amount', $data) ? $data['manual_amount'] : null;
            $roomId = array_key_exists('room_id', $data) ? ($data['room_id'] ? (int) $data['room_id'] : null) : $reservation->room_id;
            [$arrival, $departure] = [$data['arrival_date'] ?? null, $data['departure_date'] ?? null];
            unset($data['child_bands'], $data['manual_amount'], $data['room_id'], $data['arrival_date'], $data['departure_date']);

            $pricing = ['room_type_id', 'rate_code_id', 'adults'];
            $repricing = $manual !== null || collect($pricing)->contains(fn ($f) => array_key_exists($f, $data) && (string) $data[$f] !== (string) $reservation->{$f})
                || (int) array_sum($bands) !== (int) $reservation->children;

            if (isset($data['rate_code_id'])) {
                $data['currency_code'] = RateCode::query()->whereKey($data['rate_code_id'])->value('currency_code') ?? $reservation->currency_code;
            }
            if (isset($data['room_type_id']) && $reservation->status !== 'in_house') {
                $data['charge_room_type_id'] = $data['room_type_id'];
            }
            $reservation->update(array_merge($data, [
                'children' => (int) array_sum($bands), 'child_band_1' => (int) ($bands[1] ?? 0), 'child_band_2' => (int) ($bands[2] ?? 0),
                'child_band_3' => (int) ($bands[3] ?? 0), 'updated_by' => $userId,
            ]));

            [$oldFrom, $oldTo] = [$reservation->arrival_date, $reservation->departure_date];
            $from = CarbonImmutable::parse($arrival ?? $oldFrom);
            $to = CarbonImmutable::parse($departure ?? $oldTo);
            if (! $from->isSameDay($oldFrom) || ! $to->isSameDay($oldTo)) {
                if ($manual === null) {
                    $reservation = $this->changeDates($reservation->fresh(), $from->toDateString(), $to->toDateString(), $userId);
                } else {
                    // Elle fiyat verildi: tarihler değişir, bütün gelmemiş geceler aşağıda bu fiyatla yeniden kurulur.
                    if ($reservation->status === 'in_house' && ! $from->isSameDay($oldFrom)) {
                        throw new ReservationException('İçerideki misafirin giriş tarihi değiştirilemez.');
                    }
                    if ($to->lte($from)) {
                        throw new ReservationException('Çıkış tarihi girişten sonra olmalı.');
                    }
                    $reservation->update(['arrival_date' => $from->toDateString(), 'departure_date' => $to->toDateString(), 'nights' => $from->diffInDays($to)]);
                    $reservation->nightRows()->where('status', 'expected')->where(fn ($q) => $q->where('stay_date', '<', $from->toDateString())
                        ->orWhere('stay_date', '>=', $to->toDateString()))->delete();
                    $this->refreshAvailability($reservation, $from->min($oldFrom), $to->max($oldTo));
                }
            }
            if ($repricing) {
                $kept = $manual === null ? $reservation->nightRows()->where('is_overridden', true)->get()
                    ->mapWithKeys(fn ($n) => [$n->stay_date->toDateString() => (float) $n->amount])->all() : [];
                $reservation->nightRows()->where('status', 'expected')->delete();
                $this->buildNights($reservation->fresh(), $bands, (int) $reservation->extra_beds, $manual !== null ? (float) $manual : null, $kept);
            }

            $reservation = $reservation->fresh();
            if ($roomId !== $reservation->room_id) {
                if ($roomId) {
                    $reservation = $this->assignRoom($reservation, Room::query()->findOrFail($roomId), $userId);
                } elseif ($reservation->status !== 'in_house') {
                    $reservation->update(['room_id' => null]);
                    $reservation->nightRows()->update(['room_id' => null]);
                } else {
                    throw new ReservationException('İçerideki misafirin odası boşaltılamaz; oda değiştirin.');
                }
            }
            $this->refreshAvailability($reservation, $reservation->arrival_date, $reservation->departure_date);

            return $reservation->fresh(['nightRows']);
        });
    }

    public function cancel(Reservation $reservation, int $reasonCodeId, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reasonCodeId, $userId) {
            if (! in_array($reservation->status, ['inquiry', 'tentative', 'confirmed', 'waitlist'], true)) {
                throw new ReservationException('Yalnız gelmemiş rezervasyon iptal edilebilir.');
            }
            $from = $reservation->status;
            $reservation->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $userId, 'cancel_reason_code_id' => $reasonCodeId,
                'cancellation_number' => $this->numbers->next($reservation->property_id, 'cancellation'), 'room_id' => null,
            ]);
            $reservation->nightRows()->where('status', 'expected')->update(['status' => 'cancelled', 'room_id' => null]);
            $this->log($reservation, $from, 'cancelled', $userId, $reasonCodeId);
            $this->refreshAvailability($reservation, $reservation->arrival_date, $reservation->departure_date);

            return $reservation->fresh();
        });
    }

    public function assignRoom(Reservation $reservation, Room $room, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $room, $userId) {
            $this->assertActive($reservation);
            if ((int) $room->property_id !== (int) $reservation->property_id || ! $room->is_active || $room->is_pseudo) {
                throw new ReservationException("Oda {$room->number} atanamaz.");
            }
            $from = $reservation->status === 'in_house'
                ? Property::query()->find($reservation->property_id)->current_business_date->toDateString()
                : $reservation->arrival_date->toDateString();
            $to = $reservation->departure_date->toDateString();

            $clash = ReservationNight::query()->where('room_id', $room->id)->where('reservation_id', '<>', $reservation->id)
                ->whereBetween('stay_date', [$from, CarbonImmutable::parse($to)->subDay()->toDateString()])
                ->whereHas('reservation', fn ($q) => $q->whereIn('status', ['confirmed', 'in_house', 'tentative']))->exists();
            if ($clash) {
                throw new ReservationException("Oda {$room->number} bu tarihlerde başka rezervasyona atanmış.");
            }
            $outage = DB::table('pms_room_outages')->where('room_id', $room->id)->where('type', 'ooo')->whereNull('released_at')
                ->where('date_from', '<', $to)->where('date_to', '>', $from)->exists();
            if ($outage) {
                throw new ReservationException("Oda {$room->number} bu tarihlerde arızalı (OOO).");
            }

            $previous = $reservation->room_id;
            $reservation->update(['room_id' => $room->id, 'room_assigned_by' => $userId, 'room_assigned_at' => now()]);
            $reservation->nightRows()->where('stay_date', '>=', $from)->update(['room_id' => $room->id]);
            if ($reservation->status === 'in_house' && $previous && (int) $previous !== (int) $room->id) {
                DB::table('pms_reservation_room_moves')->insert([
                    'reservation_id' => $reservation->id, 'from_room_id' => $previous, 'to_room_id' => $room->id,
                    'business_date' => $from, 'user_id' => $userId, 'created_at' => now(),
                ]);
                Room::query()->whereKey($previous)->update(['fo_status' => 'vacant', 'hk_status' => 'dirty', 'current_reservation_id' => null]);
                $room->update(['fo_status' => 'occupied', 'current_reservation_id' => $reservation->id]);
            }

            return $reservation->fresh();
        });
    }

    public function checkIn(Reservation $reservation, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $userId) {
            $property = Property::query()->findOrFail($reservation->property_id);
            $today = $property->current_business_date;
            if (! in_array($reservation->status, ['confirmed', 'tentative'], true)) {
                throw new ReservationException('Bu rezervasyona giriş yapılamaz (durum: '.$reservation->status.').');
            }
            if (! $reservation->arrival_date->isSameDay($today)) {
                throw new ReservationException('Giriş yalnız geliş gününde yapılabilir.');
            }
            if (! $reservation->room_id) {
                throw new ReservationException('Girişten önce oda atanmalı.');
            }
            $room = Room::query()->whereKey($reservation->room_id)->lockForUpdate()->firstOrFail();
            if ($room->fo_status === 'occupied' && (int) $room->current_reservation_id !== (int) $reservation->id) {
                throw new ReservationException("Oda {$room->number} dolu.");
            }

            $reservation->update(['status' => 'in_house', 'checked_in_at' => now(), 'checked_in_by' => $userId, 'check_in_business_date' => $today->toDateString(), 'queued_at' => null]);
            $room->update(['fo_status' => 'occupied', 'current_reservation_id' => $reservation->id]
                + (($status = ReservationOptions::roomStatus($reservation->property_id, 'status_after_check_in')) ? ['hk_status' => $status, 'hk_status_changed_at' => now()] : []));
            if (! $reservation->folios()->where('window', 1)->exists()) {
                Folio::create([
                    'property_id' => $reservation->property_id, 'folio_number' => $this->numbers->next($reservation->property_id, 'folio'),
                    'type' => 'guest', 'reservation_id' => $reservation->id, 'window' => 1, 'payee_profile_id' => $reservation->profile_id,
                    'opened_business_date' => $today->toDateString(),
                ]);
            }
            $this->log($reservation, 'confirmed', 'in_house', $userId);

            return $reservation->fresh();
        });
    }

    public function checkOut(Reservation $reservation, ?int $userId = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $userId) {
            if ($reservation->status !== 'in_house') {
                throw new ReservationException('Yalnız içerideki misafirin çıkışı yapılabilir.');
            }
            $open = $reservation->folios()->where('status', 'open')->where('balance', '<>', 0)->pluck('folio_number');
            if ($open->isNotEmpty()) {
                throw new ReservationException('Bakiyesi kapanmamış folyo var: '.$open->implode(', '));
            }
            $today = Property::query()->findOrFail($reservation->property_id)->current_business_date->toDateString();

            $reservation->update(['status' => 'checked_out', 'checked_out_at' => now(), 'checked_out_by' => $userId, 'check_out_business_date' => $today]);
            $reservation->folios()->where('status', 'open')->update(['status' => 'closed', 'closed_business_date' => $today, 'closed_by' => $userId]);
            $reservation->nightRows()->where('status', 'expected')->where('stay_date', '>=', $today)->update(['status' => 'cancelled']); // erken çıkış
            if ($reservation->room_id) {
                $status = ReservationOptions::roomStatus($reservation->property_id, 'status_after_check_out');
                Room::query()->whereKey($reservation->room_id)->update(['fo_status' => 'vacant', 'current_reservation_id' => null]
                    + ($status ? ['hk_status' => $status, 'hk_status_changed_at' => now()] : []));
            }
            $this->log($reservation, 'in_house', 'checked_out', $userId);
            $this->refreshAvailability($reservation, CarbonImmutable::parse($today), $reservation->departure_date);

            return $reservation->fresh();
        });
    }

    /**
     * @param  array<int,int>  $bands
     * @param  array<string,float>  $overrides  tarih => elle fiyat
     */
    private function buildNights(Reservation $reservation, array $bands, int $extraBeds, ?float $manual, array $overrides = []): void
    {
        $rateCode = RateCode::query()->with('package.elements', 'roomTransactionCode')->findOrFail($reservation->rate_code_id);
        $existing = $reservation->nightRows()->pluck('stay_date')->map(fn ($d) => $d->toDateString())->all();
        $children = (int) array_sum($bands);
        $last = $reservation->is_day_use ? $reservation->arrival_date : CarbonImmutable::parse($reservation->departure_date)->subDay();

        for ($day = CarbonImmutable::parse($reservation->arrival_date); $day->lte($last); $day = $day->addDay()) {
            if (in_array($day->toDateString(), $existing, true)) {
                continue;
            }
            $quote = $this->rates->quote($rateCode, (int) $reservation->charge_room_type_id, $day, (int) $reservation->adults, $bands, $extraBeds, 0,
                $reservation->room_id ? Room::query()->whereKey($reservation->room_id)->value('number') : null);
            $override = $overrides[$day->toDateString()] ?? $manual;
            $amount = $override ?? $quote->amount;
            if ($amount === null) {
                throw new ReservationException("{$rateCode->code} fiyat kodunda {$day->format('d.m.Y')} için fiyat yok.");
            }
            $net = $this->taxes->split($rateCode->roomTransactionCode, (float) $amount, $day)['net'];
            ReservationNight::create([
                'reservation_id' => $reservation->id, 'property_id' => $reservation->property_id, 'stay_date' => $day->toDateString(),
                'room_type_id' => $reservation->room_type_id, 'charge_room_type_id' => $reservation->charge_room_type_id, 'room_id' => $reservation->room_id,
                'rate_code_id' => $rateCode->id, 'adults' => $reservation->adults, 'children' => $children,
                'free_children' => (int) $reservation->free_children, 'child_band_1' => (int) ($bands[1] ?? 0), 'child_band_2' => (int) ($bands[2] ?? 0), 'child_band_3' => (int) ($bands[3] ?? 0),
                'amount' => $amount, 'net_amount' => $net, 'package_amount' => $quote->packageAmount,
                'package_included_amount' => $rateCode->package?->is_included_in_rate ? $quote->packageAmount : 0,
                'manual_amount' => $override, 'is_overridden' => $override !== null,
                'currency_code' => $reservation->currency_code, 'market_segment_id' => $reservation->market_segment_id,
                'is_complimentary' => (bool) $reservation->is_complimentary, 'is_house_use' => (bool) $reservation->is_house_use,
            ]);
        }

        $totals = $reservation->nightRows()->where('status', '<>', 'cancelled');
        $reservation->update(['total_room_amount' => (float) $totals->sum('amount'), 'total_package_amount' => (float) $reservation->nightRows()->where('status', '<>', 'cancelled')->sum('package_amount')]);
    }

    private function assertActive(Reservation $reservation): void
    {
        if (in_array($reservation->status, ['cancelled', 'no_show', 'checked_out'], true)) {
            throw new ReservationException('Kapanmış rezervasyon değiştirilemez (durum: '.$reservation->status.').');
        }
    }

    private function refreshAvailability(Reservation $reservation, $from, $to): void
    {
        $this->availability->rebuild($reservation->property_id, CarbonImmutable::parse($from), CarbonImmutable::parse($to), (int) $reservation->room_type_id);
    }

    private function log(Reservation $reservation, ?string $from, string $to, ?int $userId, ?int $reasonCodeId = null): void
    {
        DB::table('pms_reservation_status_logs')->insert([
            'reservation_id' => $reservation->id, 'from_status' => $from, 'to_status' => $to, 'reason_code_id' => $reasonCodeId,
            'business_date' => Property::query()->find($reservation->property_id)->current_business_date->toDateString(),
            'user_id' => $userId, 'created_at' => now(),
        ]);
    }
}
