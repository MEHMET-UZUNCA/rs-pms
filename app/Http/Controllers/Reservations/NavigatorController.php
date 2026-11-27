<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Rezervasyon Navigatörü (Fidelio "Reservation Navigator"): üstte sorgu, ortada sonuç, altta seçili kaydın ağacı ve bilgi paneli.
 * Hızlı filtreler Fidelio ile aynı: Alt+1 içeride … Alt+5 ileri tarihli, Alt+6 … Alt+0 rezervasyonsuz hesaplar (FA).
 */
class NavigatorController extends Controller
{
    public const FILTERS = [
        'iceride' => ['İçeridekiler', 'alt+1'], 'gelecek' => ['Bugün gelecekler', 'alt+2'], 'gidecek' => ['Bugün gidecekler', 'alt+3'],
        'cikmis' => ['Çıkış yapmış', 'alt+4'], 'ileri' => ['İleri tarihli', 'alt+5'],
        'bekleyen' => ['Opsiyon / bekleme', ''], 'iptal' => ['İptal', ''], 'noshow' => ['No-show', ''],   // kısayolsuz (Alt+Shift Windows'ta dil değiştirir)
        'fa' => ['İçerideki FA', 'alt+6'], 'fa-acilacak' => ['Bugün açılacak FA', 'alt+7'], 'fa-kapanacak' => ['Bugün kapanacak FA', 'alt+8'],
        'fa-kapali' => ['Kapanmış FA', 'alt+9'], 'fa-ileri' => ['İleri tarihli FA', 'alt+0'],
    ];

    public const STATUSES = [
        'confirmed' => 'Kesin', 'tentative' => 'Opsiyon', 'waitlist' => 'Bekleme listesi', 'in_house' => 'İçeride',
        'checked_out' => 'Çıkış yaptı', 'cancelled' => 'İptal', 'no_show' => 'No-show',
    ];

    private const LIMIT = 500;

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $bd = CarbonImmutable::parse($property->current_business_date);
        $filter = $request->query('f');
        $hasQuery = collect(['ad', 'oda', 'no', 'firma', 'gelis', 'cikis', 'durum'])->contains(fn ($k) => filled($request->query($k)));
        $filter = array_key_exists((string) $filter, self::FILTERS) ? $filter : ($hasQuery ? null : 'iceride');
        $arrival = BusinessDateInput::parse($request->query('gelis'), $bd);
        $departure = BusinessDateInput::parse($request->query('cikis'), $bd);

        $isFa = $filter && str_starts_with($filter, 'fa');
        $rows = $isFa ? $this->financialAccounts($property->id, $filter, $bd, $request) : $this->reservations($property->id, $filter, $bd, $request, $arrival, $departure);

        return view('reservations.navigator', [
            'rows' => $rows, 'isFa' => $isFa, 'filter' => $filter, 'businessDate' => $bd, 'limit' => self::LIMIT,
            'query' => [
                'ad' => (string) $request->query('ad'), 'oda' => (string) $request->query('oda'), 'no' => (string) $request->query('no'),
                'firma' => (string) $request->query('firma'), 'durum' => (string) $request->query('durum'),
                'gelis' => $arrival?->format('d.m.Y') ?? (string) $request->query('gelis'), 'cikis' => $departure?->format('d.m.Y') ?? (string) $request->query('cikis'),
            ],
            'selected' => (int) $request->query('secili', session('selected', 0)),
            'cancelReasons' => DB::table('pms_reason_codes')->where('property_id', $property->id)->where('category', 'cancellation')
                ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /** Seçili rezervasyonun alt paneli (ağaç + bilgi). Satır seçilince sayfa yenilenmeden yüklenir. */
    public function summary(Reservation $reservation): View
    {
        $this->authorizeProperty($reservation->property_id);
        $reservation->load(['profile.communications', 'profile.addresses', 'roomType', 'room', 'rateCode', 'reservationType', 'folios']);
        $profiles = DB::table('pms_profiles')->whereIn('id', array_filter([$reservation->company_profile_id, $reservation->agent_profile_id,
            $reservation->source_profile_id, $reservation->group_profile_id]))->pluck('display_name', 'id');
        $history = DB::table('pms_reservations')->where('profile_id', $reservation->profile_id)->where('id', '<>', $reservation->id)
            ->where('status', 'checked_out')->count();

        return view('reservations._summary', ['r' => $reservation, 'profiles' => $profiles, 'history' => $history,
            'statusLabel' => __('pms.res.statuses.'.$reservation->status)]);
    }

    public function folioSummary(Folio $folio): View
    {
        $this->authorizeProperty($folio->property_id);
        $postings = DB::table('pms_postings as p')->join('pms_transaction_codes as tc', 'tc.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $folio->id)->orderByDesc('p.id')->limit(8)
            ->get(['p.business_date', 'tc.code', 'tc.name', 'p.amount', 'p.description']);

        return view('reservations._folio_summary', ['folio' => $folio, 'postings' => $postings]);
    }

    /** Fidelio "Check In" — seçili rezervasyonun girişi (oda atanmış ve geliş günü bugün olmalı). */
    public function checkIn(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorizeProperty($reservation->property_id);
        try {
            $service->checkIn($reservation, $request->user()->id);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $reservation->id);
        }

        $back = back()->with('status', __('pms.res.checked_in', ['name' => $reservation->profile?->display_name, 'room' => $reservation->room?->number]))->with('selected', $reservation->id);
        $room = $reservation->room()->first();
        if ($room && in_array($room->hk_status, ['dirty', 'pickup'], true) && ReservationOptions::get($reservation->property_id)['dirty_room_check_in'] === 'warn') {
            $back->with('error', __('pms.res.dirty_warning', ['room' => $room->number]));
        }

        return $back;
    }

    /** Rezervasyon iptali (Fidelio Cancel): neden zorunlu; iptal numarası verilir, oda ve geceler boşalır, durum geçmişine yazılır. */
    public function cancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorizeProperty($reservation->property_id);
        $data = $request->validate(['reason_code_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('pms_reason_codes', 'id')
            ->where('property_id', $reservation->property_id)->where('category', 'cancellation')]], [], ['reason_code_id' => __('pms.res.cancel_reason')]);
        try {
            $reservation = $service->cancel($reservation, (int) $data['reason_code_id'], $request->user()->id);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $reservation->id);
        }

        return back()->with('status', __('pms.res.cancelled_ok', ['name' => $reservation->profile?->display_name, 'no' => $reservation->cancellation_number]))
            ->with('selected', $reservation->id);
    }

    private function reservations(int $propertyId, ?string $filter, CarbonImmutable $bd, Request $request, ?CarbonImmutable $arrival, ?CarbonImmutable $departure)
    {
        $day = $bd->toDateString();
        $active = ['confirmed', 'tentative', 'waitlist'];
        $like = fn (string $v) => '%'.str_replace(['%', '_'], ['\%', '\_'], trim($v)).'%';

        $query = Reservation::query()->where('pms_reservations.property_id', $propertyId)
            ->with(['profile:id,display_name,nationality_code', 'roomType:id,code', 'room:id,number', 'rateCode:id,code'])
            ->select('pms_reservations.*')
            ->selectSub(DB::table('pms_folios')->selectRaw('COALESCE(SUM(balance), 0)')->whereColumn('reservation_id', 'pms_reservations.id'), 'balance')
            ->selectSub(DB::table('pms_profiles')->select('display_name')->whereColumn('id', 'pms_reservations.agent_profile_id'), 'agent_name')
            ->selectSub(DB::table('pms_profiles')->select('display_name')->whereColumn('id', 'pms_reservations.company_profile_id'), 'company_name')
            ->when($filter === 'iceride', fn (Builder $q) => $q->where('status', 'in_house'))
            ->when($filter === 'gelecek', fn (Builder $q) => $q->whereIn('status', $active)->where('arrival_date', $day))
            ->when($filter === 'gidecek', fn (Builder $q) => $q->where('status', 'in_house')->where('departure_date', '<=', $day))
            ->when($filter === 'cikmis', fn (Builder $q) => $q->where('status', 'checked_out')
                ->when(! $arrival && ! $departure, fn ($w) => $w->where('check_out_business_date', $day)))
            ->when($filter === 'ileri', fn (Builder $q) => $q->whereIn('status', $active)->where('arrival_date', '>', $day))
            // Operasyon görünümleri: opsiyon / bekleme listesi (bugün ve sonrası), iptaller (bugün iptal edilen ya da gelişi bugün ve sonrası), son 7 günün no-show'ları
            ->when($filter === 'bekleyen', fn (Builder $q) => $q->whereIn('status', ['tentative', 'waitlist', 'inquiry'])->where('arrival_date', '>=', $day))
            ->when($filter === 'iptal', fn (Builder $q) => $q->where('status', 'cancelled')->where(fn ($w) => $w->where('arrival_date', '>=', $day)->orWhereDate('cancelled_at', now()->toDateString())))
            ->when($filter === 'noshow', fn (Builder $q) => $q->where('status', 'no_show')->where('arrival_date', '>=', $bd->subDays(7)->toDateString()))
            ->when(filled($request->query('ad')), fn (Builder $q) => $q->whereHas('profile', fn ($p) => $p->where(fn ($w) => $w
                ->where('display_name', 'like', $like($request->query('ad')))->orWhere('last_name', 'like', $like($request->query('ad')))
                ->orWhere('first_name', 'like', $like($request->query('ad'))))))
            ->when(filled($request->query('oda')), fn (Builder $q) => $q->whereHas('room', fn ($r) => $r->where('number', trim($request->query('oda')))))
            ->when(filled($request->query('no')), fn (Builder $q) => $q->where('confirmation_number', 'like', trim($request->query('no')).'%'))
            ->when(filled($request->query('firma')), fn (Builder $q) => $q->where(fn ($w) => $w
                ->whereIn('agent_profile_id', DB::table('pms_profiles')->select('id')->where('display_name', 'like', $like($request->query('firma'))))
                ->orWhereIn('company_profile_id', DB::table('pms_profiles')->select('id')->where('display_name', 'like', $like($request->query('firma'))))))
            ->when($arrival, fn (Builder $q) => $q->where('arrival_date', $arrival->toDateString()))
            ->when($departure, fn (Builder $q) => $q->where('departure_date', $departure->toDateString()))
            ->when(array_key_exists((string) $request->query('durum'), self::STATUSES), fn (Builder $q) => $q->where('status', $request->query('durum')));

        return $query->orderByRaw("CASE status WHEN 'in_house' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'tentative' THEN 1 ELSE 2 END")
            ->orderBy('arrival_date')->orderBy('id')->limit(self::LIMIT)->get();
    }

    private function financialAccounts(int $propertyId, string $filter, CarbonImmutable $bd, Request $request)
    {
        $day = $bd->toDateString();

        return Folio::query()->where('property_id', $propertyId)->whereNull('reservation_id')
            ->when($filter === 'fa', fn ($q) => $q->where('status', 'open')->where(fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $day)))
            ->when($filter === 'fa-acilacak', fn ($q) => $q->where('status', 'open')->where('valid_from', $day))
            ->when($filter === 'fa-kapanacak', fn ($q) => $q->where('status', 'open')->where('valid_until', $day))
            ->when($filter === 'fa-kapali', fn ($q) => $q->where('status', 'closed'))
            ->when($filter === 'fa-ileri', fn ($q) => $q->where('status', 'open')->where('valid_from', '>', $day))
            ->when(filled($request->query('ad')), fn ($q) => $q->where('name', 'like', '%'.trim($request->query('ad')).'%'))
            ->when(filled($request->query('no')), fn ($q) => $q->where('folio_number', 'like', trim($request->query('no')).'%'))
            ->orderBy('valid_from')->orderBy('id')->limit(self::LIMIT)->get();
    }

    private function authorizeProperty(int|string|null $propertyId): void
    {
        abort_unless((int) $propertyId === (int) app(Property::class)->id, 404);
    }
}
