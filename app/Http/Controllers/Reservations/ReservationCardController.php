<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Inventory\Models\Room;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rezervasyon Kartı (Fidelio "New / Edit Reservation"): Ctrl+1 … Ctrl+0 sekmeleri. Kaydetme ReservationService üzerinden
 * (onay no, gece fiyatları, müsaitlik, oda atama kuralları).
 */
class ReservationCardController extends Controller
{
    public const GUARANTEES = ['none', 'credit_card', 'deposit', 'company', 'agent'];

    public function create(Request $request): View
    {
        $bd = CarbonImmutable::parse(app(Property::class)->current_business_date);

        return $this->card(new Reservation([
            'arrival_date' => $bd, 'departure_date' => $bd->addDay(), 'nights' => 1, 'adults' => 2, 'children' => 0,
            'room_charges_allowed' => true, 'guarantee_type' => 'none', 'status' => 'confirmed',
        ]));
    }

    public function edit(Reservation $reservation): View
    {
        $this->authorizeProperty($reservation);

        return $this->card($reservation->load(['profile.communications', 'nightRows.rateCode', 'room', 'folios']));
    }

    public function store(Request $request, ReservationService $service): RedirectResponse
    {
        $property = app(Property::class);
        $data = $this->validated($request, null);
        try {
            $reservation = DB::transaction(function () use ($service, $data, $property, $request) {
                $profileId = ($data['profile_id'] ?? null) ?: $this->newGuest($property->id, $data, $request->user()->id);
                $reservation = $service->create(array_merge($this->fields($data), [
                    'property_id' => $property->id, 'profile_id' => $profileId,
                    'arrival_date' => $data['arrival_date'], 'departure_date' => $data['departure_date'],
                    'child_bands' => [1 => (int) ($data['children'] ?? 0)], 'manual_amount' => $data['manual_amount'],
                ]), $request->user()->id);
                if (! empty($data['room_id'])) {
                    $reservation = $service->assignRoom($reservation, Room::findOrFail($data['room_id']), $request->user()->id);
                }

                return $reservation;
            });
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['reservation' => $e->getMessage()]);
        }

        return redirect()->route('reservations.edit', $reservation)
            ->with('status', __('pms.card.created', ['no' => $reservation->confirmation_number]));
    }

    public function update(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorizeProperty($reservation);
        $data = $this->validated($request, $reservation);
        try {
            $service->update($reservation, array_merge($this->fields($data), [
                'arrival_date' => $data['arrival_date'], 'departure_date' => $data['departure_date'],
                'child_bands' => [1 => (int) ($data['children'] ?? 0)], 'room_id' => $data['room_id'] ?? null,
            ] + ($data['manual_amount'] !== null ? ['manual_amount' => $data['manual_amount']] : [])), $request->user()->id);
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['reservation' => $e->getMessage()]);
        }

        return redirect()->route('reservations.edit', $reservation)->with('status', __('pms.card.saved', ['no' => $reservation->confirmation_number]));
    }

    /** Profil arama (misafir, şirket, acente) — kartın misafir kutusu için; en çok 15 sonuç. */
    public function profiles(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $types = $request->query('tur') === 'firma' ? ['company', 'travel_agent', 'agent', 'source'] : ['individual'];
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(Profile::query()->where('property_id', app(Property::class)->id)->whereIn('type', $types)
            ->where(fn ($w) => $w->where('display_name', 'like', $q.'%')->orWhere('last_name', 'like', $q.'%'))
            ->orderBy('display_name')->limit(15)->get(['id', 'display_name', 'nationality_code', 'type']));
    }

    private function validated(Request $request, ?Reservation $reservation): array
    {
        $property = app(Property::class);
        $bd = CarbonImmutable::parse($property->current_business_date);
        foreach (['arrival_date', 'departure_date'] as $field) {
            $parsed = BusinessDateInput::parse($request->input($field), $bd);
            $request->merge([$field => $parsed?->toDateString() ?? $request->input($field)]);
        }
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $property->id);

        return $request->validate([
            'profile_id' => ['nullable', 'integer', $own('pms_profiles')],
            'last_name' => [Rule::requiredIf(! $reservation && ! $request->input('profile_id')), 'nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'nationality_code' => ['nullable', Rule::exists('pms_countries', 'code')],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'arrival_date' => ['required', 'date'],
            'departure_date' => ['required', 'date', 'after_or_equal:arrival_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_type_id' => ['required', 'integer', $own('pms_room_types')],
            'room_id' => ['nullable', 'integer', $own('pms_rooms')],
            'rate_code_id' => ['required', 'integer', $own('pms_rate_codes')],
            'manual_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reservation_type_id' => ['required', 'integer', $own('pms_reservation_types')],
            'market_segment_id' => ['required', 'integer', $own('pms_market_segments')],
            'source_code_id' => ['required', 'integer', $own('pms_source_codes')],
            'company_profile_id' => ['nullable', 'integer', $own('pms_profiles')],
            'agent_profile_id' => ['nullable', 'integer', $own('pms_profiles')],
            'guarantee_type' => ['required', Rule::in(self::GUARANTEES)],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'room_charges_allowed' => ['boolean'],
            'guest_comments' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'last_name' => __('pms.card.last_name'), 'arrival_date' => __('pms.res.arrival'), 'departure_date' => __('pms.res.departure'),
            'adults' => __('pms.card.adults'), 'room_type_id' => __('pms.res.room_type'), 'rate_code_id' => __('pms.res.rate_code'),
            'reservation_type_id' => __('pms.card.reservation_type'), 'market_segment_id' => __('pms.card.market'), 'source_code_id' => __('pms.card.source'),
        ]) + ['manual_amount' => null, 'room_charges_allowed' => $request->boolean('room_charges_allowed')];
    }

    /** Kartta düzenlenen rezervasyon alanları (tarih, oda ve fiyat servis tarafından işlenir). */
    private function fields(array $data): array
    {
        return array_intersect_key($data, array_flip(['room_type_id', 'rate_code_id', 'adults', 'reservation_type_id', 'market_segment_id',
            'source_code_id', 'company_profile_id', 'agent_profile_id', 'guarantee_type', 'credit_limit', 'room_charges_allowed',
            'guest_comments', 'internal_notes']));
    }

    private function newGuest(int $propertyId, array $data, int $userId): int
    {
        $last = trim($data['last_name']);
        $first = trim((string) ($data['first_name'] ?? ''));
        $profile = Profile::create([
            'property_id' => $propertyId, 'type' => 'individual', 'last_name' => $last, 'first_name' => $first ?: null,
            'display_name' => $first ? "{$last}, {$first}" : $last, 'nationality_code' => $data['nationality_code'] ?? null,
            'created_by' => $userId, 'updated_by' => $userId,
        ]);
        foreach (['phone' => 'phone', 'email' => 'email'] as $field => $type) {
            if (! empty($data[$field])) {
                $profile->communications()->create(['type' => $type, 'value' => $data[$field]]);
            }
        }

        return $profile->id;
    }

    private function card(Reservation $reservation): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table, string $order = 'code') => DB::table($table)->where('property_id', $p)->orderBy($order)->get();

        return view('reservations.card', [
            'r' => $reservation,
            'roomTypes' => $list('pms_room_types', 'sort'), 'rateCodes' => $list('pms_rate_codes'), 'types' => $list('pms_reservation_types'),
            'segments' => $list('pms_market_segments'), 'sources' => $list('pms_source_codes'),
            'rooms' => DB::table('pms_rooms')->where('property_id', $p)->where('is_active', true)->orderBy('number')->get(['id', 'number', 'room_type_id', 'fo_status', 'hk_status']),
            'countries' => DB::table('pms_countries')->orderBy('code')->get(['code', 'name_en', 'name_tr']),
            'firms' => Profile::where('property_id', $p)->whereIn('type', ['company', 'travel_agent', 'agent', 'source'])->orderBy('display_name')->get(['id', 'display_name', 'type']),
            'linked' => Profile::whereIn('id', array_filter([$reservation->company_profile_id, $reservation->agent_profile_id,
                $reservation->source_profile_id, $reservation->group_profile_id]))->get(),
            'boards' => DB::table('pms_packages')->where('property_id', $p)->where('type', 'board')->whereNull('deleted_at')->orderBy('id')->get(),
            'history' => $reservation->exists ? DB::table('pms_reservation_status_logs')->where('reservation_id', $reservation->id)->orderBy('id')->get() : collect(),
        ]);
    }

    private function authorizeProperty(Reservation $reservation): void
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
    }
}
