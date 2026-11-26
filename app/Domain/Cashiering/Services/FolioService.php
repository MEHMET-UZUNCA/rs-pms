<?php

namespace App\Domain\Cashiering\Services;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Support\Facades\DB;

/** Folyo pencereleri (Fidelio 1–8): ihtiyaç olunca açılır; pencere 1 girişte açılır. */
class FolioService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    public function window(Reservation $reservation, int $window): Folio
    {
        if ($window < 1 || $window > 8) {
            throw new PostingException('Pencere 1 ile 8 arasında olmalı.');
        }

        return DB::transaction(function () use ($reservation, $window) {
            $existing = Folio::query()->where('reservation_id', $reservation->id)->where('window', $window)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $property = Property::query()->findOrFail($reservation->property_id);

            return Folio::create([
                'property_id' => $reservation->property_id, 'folio_number' => $this->numbers->next($reservation->property_id, 'folio'),
                'type' => 'guest', 'reservation_id' => $reservation->id, 'window' => $window, 'payee_profile_id' => $reservation->profile_id,
                'opened_business_date' => $property->current_business_date->toDateString(),
            ]);
        });
    }

    /**
     * Rezervasyonsuz hesap (Fidelio "New Financial Account", PM hesabı) açma / düzenleme.
     * Numara verilmezse sıradaki folyo numarası alınır. Başlangıç tarihi iş gününden sonraysa hesap "beklenen" olur
     * (actual_from boş); gece kapanışı o gün açar ya da durdurur (NightAuditAnswers: expected_accounts).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveAccount(int $propertyId, array $data, ?Folio $folio, ?int $userId): Folio
    {
        return DB::transaction(function () use ($propertyId, $data, $folio, $userId) {
            $bd = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();
            $number = trim((string) ($data['folio_number'] ?? ''));
            unset($data['folio_number']);

            if ($folio) {
                if ($number !== '' && $number !== $folio->folio_number) {
                    $data['folio_number'] = $number;
                }
                if (! $folio->actual_from && (! $data['valid_from'] || $data['valid_from'] <= $bd)) {
                    $data['actual_from'] = $bd;
                }
                $folio->update($data);

                return $folio->fresh();
            }

            $from = $data['valid_from'] ?: $bd;
            $data['valid_from'] = $from;

            return Folio::create($data + [
                'property_id' => $propertyId, 'folio_number' => $number !== '' ? $number : $this->numbers->next($propertyId, 'folio'),
                'type' => 'house_account', 'window' => 1, 'status' => 'open',
                'actual_from' => $from <= $bd ? $bd : null, 'opened_business_date' => $bd, 'opened_by' => $userId,
            ]);
        });
    }
}
