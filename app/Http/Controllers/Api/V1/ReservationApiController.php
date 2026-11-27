<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Rates\Services\RateCalculator;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Web API v1 — web sitesinden rezervasyon (docs/11-web-api.md §11.5):
 *  - POST /reservations: fiyat PMS'te yeniden hesaplanır (web'in tutarına güvenilmez), müsaitlik ve fiyat kodu kuralları denetlenir;
 *    aynı external_reference ile tekrar gelirse aynı rezervasyon döner (idempotent).
 *  - GET /reservations/{onay no}?last_name=…: durum. POST /reservations/{onay no}/cancel: iptal (giriş gününden önce).
 * Rezervasyon tipi / pazar / kaynak kodları Ayarlar → Rezervasyon seçenekleri → Web sitesi rezervasyonlarından gelir; boşsa 503.
 */
class ReservationApiController extends Controller
{
    public function store(Request $request, ReservationService $service, RateCalculator $calculator, AvailabilityService $availability): JsonResponse
    {
        $p = app(Property::class);
        $codes = $this->codes($p->id);
        if (! $codes) {
            return response()->json(['error' => 'not_configured', 'message' => 'Web reservations are not configured in the PMS.'], 503);
        }
        $data = $request->validate([
            'external_reference' => ['required', 'string', 'max:60'],
            'arrival' => ['required', 'date_format:Y-m-d'], 'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'adults' => ['required', 'integer', 'between:1,8'], 'children' => ['nullable', 'array', 'max:6'], 'children.*' => ['integer', 'between:0,17'],
            'room_type' => ['required', 'string', 'max:10'], 'rate_code' => ['required', 'string', 'max:20'],
            'guest.last_name' => ['required', 'string', 'max:100'], 'guest.first_name' => ['nullable', 'string', 'max:100'],
            'guest.email' => ['required', 'email', 'max:190'], 'guest.phone' => ['nullable', 'string', 'max:40'],
            'guest.nationality' => ['nullable', 'string', 'size:2', 'exists:pms_countries,code'], 'guest.language' => ['nullable', 'string', 'size:2'],
            'eta' => ['nullable', 'date_format:H:i'], 'comments' => ['nullable', 'string', 'max:1000'],
        ]);
        $reference = 'WEB:'.$data['external_reference'];
        if ($existing = Reservation::query()->where('property_id', $p->id)->where('external_reference', $reference)->first()) {
            return response()->json(['data' => $this->present($existing), 'duplicate' => true]);
        }

        $arrival = CarbonImmutable::parse($data['arrival']);
        $departure = CarbonImmutable::parse($data['departure']);
        $today = $p->current_business_date->toImmutable();
        if ($arrival->lt($today) || $arrival->diffInDays($departure) > 30) {
            return $this->fail('Arrival is in the past or the stay is longer than 30 nights.');
        }
        $type = DB::table('pms_room_types')->where('property_id', $p->id)->where('code', $data['room_type'])->where('is_bookable_online', true)->whereNull('deleted_at')->first();
        $rate = RateCode::query()->where('property_id', $p->id)->where('code', $data['rate_code'])->where('is_active', true)->where('is_public', true)->first();
        if (! $type || ! $rate) {
            return $this->fail('Unknown room type or rate code.');
        }
        $ages = array_map('intval', $data['children'] ?? []);
        if ((int) $data['adults'] + count($ages) > (int) $type->max_occupancy) {
            return $this->fail('Too many guests for this room type.');
        }
        $availability->rebuild($p->id, $arrival, $departure->subDay(), (int) $type->id);
        $free = (int) DB::table('pms_inventory_daily')->where('property_id', $p->id)->where('room_type_id', $type->id)
            ->whereBetween('date', [$arrival->toDateString(), $departure->subDay()->toDateString()])->min('available');
        if ($free < 1) {
            return response()->json(['error' => 'not_available', 'message' => 'The room type is not available for these dates.'], 409);
        }
        $bands = $this->childBands($p->id, $ages);
        for ($day = $arrival; $day->lt($departure); $day = $day->addDay()) {
            if (! $calculator->quote($rate, (int) $type->id, $day, (int) $data['adults'], $bands)->found()) {
                return response()->json(['error' => 'no_rate', 'message' => 'No price for '.$day->toDateString().'.'], 409);
            }
        }

        try {
            $reservation = DB::transaction(function () use ($p, $data, $codes, $type, $rate, $arrival, $departure, $bands, $reference, $service) {
                $profileId = $this->guestProfile($p->id, $data['guest']);

                return $service->create([
                    'property_id' => $p->id, 'profile_id' => $profileId, 'room_type_id' => $type->id, 'rate_code_id' => $rate->id,
                    'arrival_date' => $arrival->toDateString(), 'departure_date' => $departure->toDateString(), 'adults' => (int) $data['adults'],
                    'child_bands' => $bands, 'reservation_type_id' => $codes['type'], 'market_segment_id' => $codes['market'], 'source_code_id' => $codes['source'],
                    'status' => $codes['status'], 'external_reference' => $reference, 'eta' => $data['eta'] ?? null, 'guest_comments' => $data['comments'] ?? null,
                    'booker_name' => 'Web',
                ], null);
            });
        } catch (ReservationException $e) {
            return response()->json(['error' => 'rejected', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->present($reservation)], 201);
    }

    public function show(Request $request, string $confirmation): JsonResponse
    {
        $reservation = $this->find($request, $confirmation);

        return $reservation ? response()->json(['data' => $this->present($reservation)]) : response()->json(['error' => 'not_found'], 404);
    }

    public function cancel(Request $request, string $confirmation, ReservationService $service): JsonResponse
    {
        $reservation = $this->find($request, $confirmation);
        if (! $reservation) {
            return response()->json(['error' => 'not_found'], 404);
        }
        if (! $reservation->arrival_date->gt(app(Property::class)->current_business_date)) {
            return response()->json(['error' => 'too_late', 'message' => 'Cancellation on or after the arrival day must be made by the hotel.'], 409);
        }
        $reason = DB::table('pms_reason_codes')->where('property_id', $reservation->property_id)->where('category', 'cancellation')->orderByRaw("CASE code WHEN 'GUEST' THEN 0 ELSE 1 END")->value('id');
        try {
            $reservation = $service->cancel($reservation, (int) $reason, null);
        } catch (ReservationException $e) {
            return response()->json(['error' => 'rejected', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->present($reservation)]);
    }

    /** Onay no + soyad (büyük / küçük harf farkı yok); yalnız web'den gelen rezervasyonlar. */
    private function find(Request $request, string $confirmation): ?Reservation
    {
        $last = mb_strtolower(trim((string) $request->input('last_name', $request->query('last_name', ''))));
        if ($last === '') {
            return null;
        }
        $reservation = Reservation::query()->where('property_id', app(Property::class)->id)->where('confirmation_number', $confirmation)
            ->where('external_reference', 'like', 'WEB:%')->with('profile')->first();

        return $reservation && mb_strtolower((string) $reservation->profile?->last_name) === $last ? $reservation : null;
    }

    private function present(Reservation $r): array
    {
        $r->loadMissing(['roomType', 'rateCode', 'profile']);

        return [
            'confirmation_number' => $r->confirmation_number, 'status' => $r->status, 'external_reference' => Str::after((string) $r->external_reference, 'WEB:'),
            'arrival' => $r->arrival_date->toDateString(), 'departure' => $r->departure_date->toDateString(), 'nights' => (int) $r->nights,
            'room_type' => $r->roomType?->code, 'rate_code' => $r->rateCode?->code, 'adults' => (int) $r->adults, 'children' => (int) $r->children,
            'currency' => $r->currency_code, 'total' => round((float) $r->total_room_amount, 2), 'guest' => $r->profile?->display_name,
            'cancellation_number' => $r->cancellation_number,
        ];
    }

    /** @return array{type: int, market: int, source: int, status: string}|null */
    private function codes(int $propertyId): ?array
    {
        $o = ReservationOptions::get($propertyId);
        $id = fn (string $table, ?string $code) => $code ? DB::table($table)->where('property_id', $propertyId)->where('code', $code)->value('id') : null;
        $codes = ['type' => $id('pms_reservation_types', $o['web_reservation_type'] ?? null), 'market' => $id('pms_market_segments', $o['web_market'] ?? null),
            'source' => $id('pms_source_codes', $o['web_source'] ?? null)];

        return in_array(null, $codes, true) ? null : $codes + ['status' => ($o['web_status'] ?? 'confirmed') === 'tentative' ? 'tentative' : 'confirmed'];
    }

    /** Misafir profili: aynı e-postalı bireysel profil varsa o, yoksa yeni. */
    private function guestProfile(int $propertyId, array $guest): int
    {
        $email = mb_strtolower(trim($guest['email']));
        $existing = DB::table('pms_profiles as p')->join('pms_profile_communications as c', 'c.profile_id', '=', 'p.id')
            ->where('p.property_id', $propertyId)->where('p.type', 'individual')->whereNull('p.merged_into_id')->where('c.type', 'email')->where('c.value_normalized', $email)
            ->value('p.id');
        if ($existing) {
            return (int) $existing;
        }
        $last = trim($guest['last_name']);
        $first = trim((string) ($guest['first_name'] ?? ''));
        $id = DB::table('pms_profiles')->insertGetId([
            'property_id' => $propertyId, 'type' => 'individual', 'last_name' => $last, 'first_name' => $first ?: null,
            'display_name' => $first ? "{$last}, {$first}" : $last, 'nationality_code' => $guest['nationality'] ?? null, 'language_code' => $guest['language'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pms_profile_communications')->insert(['profile_id' => $id, 'type' => 'email', 'value' => $guest['email'], 'value_normalized' => $email, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        if (! empty($guest['phone'])) {
            DB::table('pms_profile_communications')->insert(['profile_id' => $id, 'type' => 'mobile', 'value' => $guest['phone'],
                'value_normalized' => preg_replace('/[^\d+]/', '', $guest['phone']), 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function childBands(int $propertyId, array $ages): array
    {
        $bands = DB::table('pms_child_age_bands')->where('property_id', $propertyId)->orderBy('band')->get();
        $counts = [];
        foreach ($ages as $age) {
            $band = $bands->first(fn ($b) => $age >= $b->age_from && $age <= $b->age_to)?->band ?? $bands->last()?->band ?? 1;
            $counts[$band] = ($counts[$band] ?? 0) + 1;
        }

        return $counts;
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['error' => 'validation', 'message' => $message], 422);
    }
}
