<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fidelio Fixed Charges: rezervasyona bağlı, gece kapanışında kendiliğinden folyoya yazılan ücretler (otopark, ek yatak, bebek karyolası,
 * evcil hayvan …). Sıklık: once (başlangıç gününde bir kez), daily (her gece), weekly (başlangıç gününün haftanın aynı gününde).
 * Kayıt, kapanan iş gününe (stay_date) yazılır; aynı gün ikinci kez yazılmaz (idempotency anahtarı + last_posted_date).
 */
class FixedChargeService
{
    public const FREQUENCIES = ['once', 'daily', 'weekly'];

    public function __construct(private readonly PostingService $postings, private readonly FolioService $folios) {}

    /** @param  array{transaction_code_id: int, description?: ?string, quantity: float|int|string, amount: float|int|string, frequency: string, date_from: string, date_to: string, folio_window?: int} $data */
    public function add(Reservation $reservation, array $data): int
    {
        if (in_array($reservation->status, ['cancelled', 'no_show', 'checked_out'], true)) {
            throw new ReservationException('Kapanmış rezervasyona sabit ücret eklenemez.');
        }
        $from = CarbonImmutable::parse($data['date_from']);
        $to = CarbonImmutable::parse($data['date_to']);
        $lastNight = $reservation->departure_date->toImmutable()->subDay();
        if ($from->lt($reservation->arrival_date->toImmutable()) || $to->gt($lastNight) || $to->lt($from)) {
            throw new ReservationException('Sabit ücret tarihleri konaklama geceleri içinde olmalı ('.$reservation->arrival_date->format('d.m.Y').' – '.$lastNight->format('d.m.Y').').');
        }

        return DB::table('pms_fixed_charges')->insertGetId([
            'reservation_id' => $reservation->id, 'transaction_code_id' => $data['transaction_code_id'], 'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'], 'amount' => $data['amount'], 'frequency' => $data['frequency'],
            'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'folio_window' => $data['folio_window'] ?? 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function delete(Reservation $reservation, int $id): void
    {
        DB::table('pms_fixed_charges')->where('reservation_id', $reservation->id)->where('id', $id)->delete();
    }

    /** Ücret bu gece yazılacak mı? */
    public static function isDue(object $charge, CarbonImmutable $date): bool
    {
        $from = CarbonImmutable::parse($charge->date_from);
        if ($date->lt($from) || $date->gt(CarbonImmutable::parse($charge->date_to))) {
            return false;
        }

        return match ($charge->frequency) {
            'once' => $date->isSameDay($from),
            'weekly' => $date->dayOfWeek === $from->dayOfWeek,
            default => true,
        };
    }

    /**
     * Gece kapanışı: içerideki rezervasyonların o geceye düşen sabit ücretlerini yazar.
     *
     * @return array{fixed_charges_posted: int, fixed_charges_amount: float}
     */
    public function postForDate(int $propertyId, string $businessDate, ?int $userId): array
    {
        $date = CarbonImmutable::parse($businessDate);
        $charges = DB::table('pms_fixed_charges as f')->join('pms_reservations as r', 'r.id', '=', 'f.reservation_id')
            ->where('r.property_id', $propertyId)->where('r.status', 'in_house')
            ->where('f.date_from', '<=', $businessDate)->where('f.date_to', '>=', $businessDate)
            ->where(fn ($q) => $q->whereNull('f.last_posted_date')->orWhere('f.last_posted_date', '<', $businessDate))
            ->orderBy('f.id')->get(['f.*']);
        $count = 0;
        $total = 0.0;
        foreach ($charges as $charge) {
            if (! self::isDue($charge, $date)) {
                continue;
            }
            $reservation = Reservation::query()->findOrFail($charge->reservation_id);
            $folio = Folio::query()->where('reservation_id', $reservation->id)->where('window', $charge->folio_window)->where('status', 'open')->first()
                ?? $this->folios->window($reservation, (int) $charge->folio_window);
            $code = TransactionCode::query()->findOrFail($charge->transaction_code_id);
            $amount = round((float) $charge->amount * (float) $charge->quantity, 2);
            $this->postings->charge($folio, $code, (float) $charge->amount, (float) $charge->quantity, $charge->description ?: $code->name, [
                'source' => 'fixed_charge', 'posted_by' => $userId, 'stay_date' => $businessDate, 'idempotency_key' => "fixed:{$businessDate}:{$charge->id}",
            ]);
            DB::table('pms_fixed_charges')->where('id', $charge->id)->update(['last_posted_date' => $businessDate, 'updated_at' => now()]);
            $count++;
            $total += $amount;
        }

        return ['fixed_charges_posted' => $count, 'fixed_charges_amount' => round($total, 2)];
    }
}
