<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Depozito / ön ödeme takibi (Fidelio Deposit Requests): rezervasyona son tarih ve tutarla istek açılır; folyoya depozito kodu (992 / 904)
 * ile ödeme atılınca en eski bekleyen istek "ödendi" olur. Son tarihi geçen bekleyen istekler navigatörde "Depozito" görünümünde listelenir.
 */
class DepositService
{
    public function request(Reservation $reservation, string $dueDate, float $amount, string $currency): int
    {
        if (in_array($reservation->status, ['cancelled', 'no_show', 'checked_out'], true)) {
            throw new ReservationException(__('pms.err.closed_reservation', ['status' => __('pms.res.statuses.'.$reservation->status)]));
        }
        $id = DB::table('pms_deposit_requests')->insertGetId(['reservation_id' => $reservation->id, 'due_date' => $dueDate, 'amount' => round($amount, 2),
            'currency_code' => $currency, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->refresh($reservation);
        Audit::record('reservation', $reservation->id, 'deposit_requested', null, ['due' => $dueDate, 'amount' => $amount, 'currency' => $currency], null, $reservation->property_id);

        return $id;
    }

    public function setStatus(Reservation $reservation, int $requestId, string $status, ?int $postingId = null): void
    {
        DB::table('pms_deposit_requests')->where('reservation_id', $reservation->id)->where('id', $requestId)
            ->update(['status' => $status, 'paid_posting_id' => $postingId, 'updated_at' => now()]);
        $this->refresh($reservation);
        Audit::record('reservation', $reservation->id, 'deposit_'.$status, null, ['request' => $requestId, 'posting' => $postingId], null, $reservation->property_id);
    }

    /** Depozito ödemesi atılınca: en eski bekleyen istek ödendi sayılır. */
    public function matchPayment(Reservation $reservation, int $postingId): ?int
    {
        $pending = DB::table('pms_deposit_requests')->where('reservation_id', $reservation->id)->where('status', 'pending')->orderBy('due_date')->value('id');
        if ($pending) {
            $this->setStatus($reservation, (int) $pending, 'paid', $postingId);
        }

        return $pending ? (int) $pending : null;
    }

    /** Rezervasyondaki özet: bekleyen toplam ve en yakın son tarih. */
    private function refresh(Reservation $reservation): void
    {
        $pending = DB::table('pms_deposit_requests')->where('reservation_id', $reservation->id)->where('status', 'pending');
        $reservation->update(['deposit_required' => round((float) (clone $pending)->sum('amount'), 2), 'deposit_due_date' => (clone $pending)->min('due_date')]);
    }
}
