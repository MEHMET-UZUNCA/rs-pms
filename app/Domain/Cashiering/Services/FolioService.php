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
}
