<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Inventory\Models\Room;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationExtrasService;
use App\Domain\Reservations\Services\ReservationService;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $property = app(Property::class);
        $bd = CarbonImmutable::parse($property->current_business_date);
        // Oda planından: ?oda=&gelis=&cikis= (boş hücrelerde sürükleyerek)
        $arrival = BusinessDateInput::parse((string) $request->query('gelis', ''), $bd);
        $arrival = $arrival && $arrival->gte($bd) ? $arrival : $bd;
        $departure = BusinessDateInput::parse((string) $request->query('cikis', ''), $bd);
        $departure = $departure && $departure->gt($arrival) ? $departure : $arrival->addDay();
        $room = $request->query('oda') ? Room::query()->where('property_id', $property->id)->whereKey((int) $request->query('oda'))->first() : null;

        return $this->card(new Reservation(array_filter([
            'arrival_date' => $arrival, 'departure_date' => $departure, 'nights' => $arrival->diffInDays($departure), 'adults' => 2, 'children' => 0,
            'room_charges_allowed' => true, 'guarantee_type' => 'none', 'status' => 'confirmed',
            'room_id' => $room?->id,
            'room_type_id' => $room?->room_type_id ?? DB::table('pms_room_types')->where('property_id', $property->id)->where('id', (int) $request->query('tip'))->value('id'),
        ], fn ($v) => $v !== null)));
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
                $this->saveExtras($reservation, $data);
                $this->assertCapacity($reservation, $request);

                return $reservation;
            });
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['reservation' => $e->getMessage()])->with('overbooking', str_starts_with($e->getMessage(), self::OVERBOOK_MARK));
        }

        return redirect()->route('reservations.edit', $reservation)
            ->with('status', __('pms.card.created', ['no' => $reservation->confirmation_number]));
    }

    public function update(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorizeProperty($reservation);
        $data = $this->validated($request, $reservation);
        try {
            $before = [$reservation->arrival_date->toDateString(), $reservation->departure_date->toDateString(), (int) $reservation->room_type_id];
            DB::transaction(function () use ($service, $reservation, $data, $request, $before) {
                $service->update($reservation, array_merge($this->fields($data), [
                    'arrival_date' => $data['arrival_date'], 'departure_date' => $data['departure_date'],
                    'child_bands' => [1 => (int) ($data['children'] ?? 0)], 'room_id' => $data['room_id'] ?? null,
                    'room_move_reason_id' => $data['room_move_reason_id'] ?? null,
                ] + ($data['manual_amount'] !== null ? ['manual_amount' => $data['manual_amount']] : [])), $request->user()->id);
                $this->saveExtras($reservation->fresh(), $data);
                $after = $reservation->fresh();
                if ($before !== [$after->arrival_date->toDateString(), $after->departure_date->toDateString(), (int) $after->room_type_id]) {
                    $this->assertCapacity($after, $request);   // yalnız tarih ya da oda tipi değiştiyse (not, kart gibi değişiklikler engellenmez)
                }
            });
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['reservation' => $e->getMessage()])->with('overbooking', str_starts_with($e->getMessage(), self::OVERBOOK_MARK));
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
            ->where('status', '!=', 'inactive')->whereNull('merged_into_id')
            ->orderBy('display_name')->limit(15)->get(['id', 'display_name', 'nationality_code', 'type', 'status', 'blacklist_reason']));
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
            'eta' => ['nullable', 'date_format:H:i'], 'etd' => ['nullable', 'date_format:H:i'],
            'features' => ['nullable', 'array'], 'features.*' => ['integer', Rule::exists('pms_room_features', 'id')->where('property_id', $property->id)],
            'card_holder' => ['nullable', 'string', 'max:150'], 'card_brand' => ['nullable', Rule::in(ReservationExtrasService::CARD_BRANDS)],
            'card_last4' => ['nullable', 'regex:/^\d{4}$/'], 'card_expiry' => ['nullable', 'regex:#^(0[1-9]|1[0-2])/\d{2}$#'],
            'room_move_reason_id' => ['nullable', 'integer', Rule::exists('pms_reason_codes', 'id')->where('property_id', $property->id)->where('category', 'room_move')],
            'guest_comments' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ], ['card_last4.regex' => __('pms.card.cc_last4_only')], [
            'last_name' => __('pms.card.last_name'), 'arrival_date' => __('pms.res.arrival'), 'departure_date' => __('pms.res.departure'),
            'adults' => __('pms.card.adults'), 'room_type_id' => __('pms.res.room_type'), 'rate_code_id' => __('pms.res.rate_code'),
            'reservation_type_id' => __('pms.card.reservation_type'), 'market_segment_id' => __('pms.card.market'), 'source_code_id' => __('pms.card.source'),
        ]) + ['manual_amount' => null, 'room_charges_allowed' => $request->boolean('room_charges_allowed'),
            'features_sent' => $request->boolean('features_sent'), 'card_remove' => $request->boolean('card_remove')];
    }

    /** Kartta düzenlenen rezervasyon alanları (tarih, oda ve fiyat servis tarafından işlenir). */
    private function fields(array $data): array
    {
        return array_intersect_key($data, array_flip(['room_type_id', 'rate_code_id', 'adults', 'reservation_type_id', 'market_segment_id',
            'source_code_id', 'company_profile_id', 'agent_profile_id', 'guarantee_type', 'credit_limit', 'room_charges_allowed', 'eta', 'etd',
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
            'changes' => $reservation->exists ? DB::table('pms_audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->where('a.auditable_type', 'reservation')
                ->where('a.auditable_id', $reservation->id)->where('a.action', '!=', 'created')->orderByDesc('a.id')->limit(50)->get(['a.*', 'u.username']) : collect(),
            'history' => $reservation->exists ? DB::table('pms_reservation_status_logs')->where('reservation_id', $reservation->id)->orderBy('id')->get() : collect(),
            'fixedCharges' => $reservation->exists ? DB::table('pms_fixed_charges as f')->join('pms_transaction_codes as t', 't.id', '=', 'f.transaction_code_id')
                ->where('f.reservation_id', $reservation->id)->orderBy('f.date_from')->orderBy('f.id')->get(['f.*', 't.code', 't.name as code_name']) : collect(),
            'chargeCodes' => DB::table('pms_transaction_codes')->where('property_id', $p)->whereNotIn('type', ['payment', 'deposit'])->where('is_discount', false)
                ->where('is_active', true)->where('manual_post_allowed', true)->whereNull('deleted_at')->orderBy('code')->get(['id', 'code', 'name', 'default_price']),
            'roomFeatures' => DB::table('pms_room_features')->where('property_id', $p)->orderBy('code')->get(['id', 'code', 'name']),
            'selectedFeatures' => $reservation->exists ? app(ReservationExtrasService::class)->features($reservation) : [],
            'matchingRooms' => $reservation->exists && ! in_array($reservation->status, ['cancelled', 'no_show', 'checked_out'], true)
                ? app(ReservationExtrasService::class)->matchingRooms($p, (int) $reservation->room_type_id,
                    $reservation->arrival_date->toDateString(), $reservation->departure_date->toDateString(),
                    app(ReservationExtrasService::class)->features($reservation), $reservation->id) : collect(),
            'card' => $reservation->payment_token_id ? DB::table('pms_payment_tokens')->where('id', $reservation->payment_token_id)->first(['brand', 'last4', 'expiry_month', 'expiry_year', 'holder_name']) : null,
            'deposits' => $reservation->exists ? DB::table('pms_deposit_requests')->where('reservation_id', $reservation->id)->orderBy('due_date')->get() : collect(),
            'currencies' => DB::table('pms_currencies')->where('is_active', true)->orderBy('sort')->pluck('code'),
            'items' => DB::table('pms_items')->where('property_id', $p)->orderBy('code')->get(['id', 'code', 'name', 'quantity_total', 'price']),
            'reservationItems' => $reservation->exists ? DB::table('pms_reservation_items as ri')->join('pms_items as i', 'i.id', '=', 'ri.item_id')
                ->where('ri.reservation_id', $reservation->id)->orderBy('ri.date_from')->get(['ri.*', 'i.code', 'i.name']) : collect(),
            'boardElements' => $reservation->exists && $reservation->rateCode?->package_id ? DB::table('pms_package_elements as e')->join('pms_transaction_codes as t', 't.id', '=', 'e.transaction_code_id')
                ->where('e.package_id', $reservation->rateCode->package_id)->get(['e.calc', 'e.amount_adult', 'e.amount_child', 't.code', 't.name']) : collect(),
            'moveReasons' => DB::table('pms_reason_codes')->where('property_id', $p)->where('category', 'room_move')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'checkOutTime' => ReservationOptions::get($p)['check_out_time'],
        ]);
    }

    public const OVERBOOK_MARK = '⚠';

    /**
     * Overbooking denetimi: kayıttan sonra oda tipinin müsaitliği eksiye düştüyse (kesin satış + kesin blok > oda − arızalı) kayıt geri alınır;
     * kullanıcı "yine de kaydet"i işaretlerse kaydedilir. Opsiyon ve sayılmayan rezervasyon tipleri müsaitliği düşürmez, denetlenmez.
     */
    private function assertCapacity(Reservation $reservation, Request $request): void
    {
        $mayOverbook = Gate::allows('permission', 'reservations.overbooking');
        if (($request->boolean('overbook') && $mayOverbook) || in_array($reservation->status, ['cancelled', 'no_show', 'checked_out'], true)) {
            return;
        }
        // Overbooking sınırı (Reservation → Overbooking) içinde kalan fazla satış uyarısız kaydedilir
        $short = DB::table('pms_inventory_daily')->where('property_id', $reservation->property_id)->where('room_type_id', $reservation->room_type_id)
            ->where('date', '>=', $reservation->arrival_date->toDateString())->where('date', '<', $reservation->departure_date->toDateString())
            ->whereRaw('CAST(available AS SIGNED) + CAST(overbooking_limit AS SIGNED) < 0')->orderBy('date')->get(['date', 'available']);
        if ($short->isNotEmpty()) {
            throw new ReservationException(self::OVERBOOK_MARK.' '.__($mayOverbook ? 'pms.card.overbooking' : 'pms.card.overbooking_denied', ['type' => $reservation->roomType?->code,
                'dates' => $short->map(fn ($d) => Carbon::parse($d->date)->format('d.m').' ('.$d->available.')')->implode(', ')]));
        }
    }

    /** Features ve garanti kartı (kartın ana formuyla birlikte kaydedilir). */
    private function saveExtras(Reservation $reservation, array $data): void
    {
        $extras = app(ReservationExtrasService::class);
        if ($data['features_sent'] ?? false) {
            $extras->syncFeatures($reservation, $data['features'] ?? []);
        }
        $extras->saveCard($reservation, $data);
    }

    private function authorizeProperty(Reservation $reservation): void
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
    }
}
