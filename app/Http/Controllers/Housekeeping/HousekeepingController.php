<?php

namespace App\Http\Controllers\Housekeeping;

use App\Domain\Core\Models\Property;
use App\Domain\Housekeeping\Exceptions\HousekeepingException;
use App\Domain\Housekeeping\Services\HousekeepingService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kat Hizmetleri (Fidelio "Housekeeping" / Room Status): odaların temizlik durumu, işaretleri, boş/dolu bilgisi, bugünkü giriş/çıkış,
 * birden çok odaya durum verme, OOO/OOS girişi ve kaldırma.
 */
class HousekeepingController extends Controller
{
    public const FILTERS = ['vd' => 'vacant_dirty', 'vc' => 'vacant_clean', 'od' => 'occupied_dirty', 'oc' => 'occupied_clean', 'ins' => 'inspected',
        'arr' => 'arrivals', 'dep' => 'departures', 'ooo' => 'out_of_order'];

    public function __construct(private readonly HousekeepingService $hk) {}

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $bd = $property->current_business_date->toDateString();
        $filter = array_key_exists((string) $request->query('f'), self::FILTERS) ? $request->query('f') : null;
        $floor = (int) $request->query('kat');
        $block = (int) $request->query('blok');

        $rooms = DB::table('pms_rooms as r')->join('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->leftJoin('pms_floors as f', 'f.id', '=', 'r.floor_id')->leftJoin('pms_buildings as b', 'b.id', '=', 'r.building_id')
            ->leftJoin('pms_reservations as ih', fn ($j) => $j->on('ih.room_id', '=', 'r.id')->where('ih.status', 'in_house'))
            ->leftJoin('pms_profiles as ihp', 'ihp.id', '=', 'ih.profile_id')
            ->where('r.property_id', $property->id)->where('r.is_active', true)->where('r.is_pseudo', false)
            ->when($floor, fn ($q) => $q->where('r.floor_id', $floor))->when($block, fn ($q) => $q->where('r.building_id', $block))
            ->orderBy('r.number')
            ->get(['r.id', 'r.number', 'r.fo_status', 'r.hk_status', 'r.hk_flag', 'r.service_status', 'r.hk_status_changed_at', 't.code as type',
                'f.code as floor', 'b.code as block', 'ihp.display_name as guest', 'ih.departure_date', 'ih.adults', 'ih.children']);

        $arrivals = DB::table('pms_reservations as a')->join('pms_profiles as p', 'p.id', '=', 'a.profile_id')
            ->where('a.property_id', $property->id)->whereIn('a.status', ['confirmed', 'tentative'])->where('a.arrival_date', $bd)->whereNotNull('a.room_id')
            ->pluck('p.display_name', 'a.room_id');
        $outages = DB::table('pms_room_outages')->whereIn('room_id', $rooms->pluck('id'))->whereNull('released_at')->where('date_to', '>', $bd)
            ->orderBy('date_from')->get()->groupBy('room_id');

        $rooms = $rooms->map(function ($r) use ($arrivals, $bd, $outages) {
            $r->arrival = $arrivals[$r->id] ?? null;
            $r->departing = $r->departure_date && $r->departure_date <= $bd;
            $r->outage = $outages[$r->id][0] ?? null;

            return $r;
        });
        $counts = [
            'vacant_clean' => $rooms->where('fo_status', 'vacant')->whereIn('hk_status', ['clean', 'inspected'])->count(),
            'vacant_dirty' => $rooms->where('fo_status', 'vacant')->whereIn('hk_status', ['dirty', 'pickup'])->count(),
            'occupied_clean' => $rooms->where('fo_status', 'occupied')->whereIn('hk_status', ['clean', 'inspected'])->count(),
            'occupied_dirty' => $rooms->where('fo_status', 'occupied')->whereIn('hk_status', ['dirty', 'pickup'])->count(),
            'inspected' => $rooms->where('hk_status', 'inspected')->count(),
            'arrivals' => $rooms->whereNotNull('arrival')->count(),
            'departures' => $rooms->where('departing', true)->count(),
            'out_of_order' => $rooms->where('service_status', '!=', 'in_service')->count(),
        ];
        $rooms = $rooms->filter(fn ($r) => match ($filter) {
            'vd' => $r->fo_status === 'vacant' && in_array($r->hk_status, ['dirty', 'pickup'], true),
            'vc' => $r->fo_status === 'vacant' && in_array($r->hk_status, ['clean', 'inspected'], true),
            'od' => $r->fo_status === 'occupied' && in_array($r->hk_status, ['dirty', 'pickup'], true),
            'oc' => $r->fo_status === 'occupied' && in_array($r->hk_status, ['clean', 'inspected'], true),
            'ins' => $r->hk_status === 'inspected', 'arr' => (bool) $r->arrival, 'dep' => $r->departing,
            'ooo' => $r->service_status !== 'in_service' || $r->outage, default => true,
        })->values();

        return view('housekeeping.index', [
            'rooms' => $rooms, 'counts' => $counts, 'filter' => $filter, 'floor' => $floor, 'block' => $block, 'businessDate' => $property->current_business_date,
            'codes' => DB::table('pms_hk_status_codes')->where('property_id', $property->id)->where('is_active', true)->orderBy('sort')->get(),
            'floors' => DB::table('pms_floors')->where('property_id', $property->id)->orderBy('sort')->get(['id', 'name']),
            'blocks' => DB::table('pms_buildings')->where('property_id', $property->id)->orderBy('code')->get(['id', 'name']),
            'reasons' => DB::table('pms_reason_codes')->where('property_id', $property->id)->where('category', 'room_status')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'house' => app(\App\Domain\Housekeeping\Services\HouseStatusService::class)->compute($property->id),   // alt durum çubuğu (Fidelio)
        ]);
    }

    public function status(Request $request): RedirectResponse
    {
        $property = app(Property::class);
        $data = $request->validate(['rooms' => ['required', 'array', 'min:1'], 'rooms.*' => ['integer'], 'code' => ['required', 'string', 'max:10']],
            ['rooms.required' => __('pms.hkscreen.select_rooms')]);
        try {
            $count = $data['code'] === 'CLEAR'
                ? $this->hk->clearFlag($property->id, $data['rooms'], $request->user()->id)
                : $this->hk->setStatus($property->id, $data['rooms'], $data['code'], $request->user()->id);
        } catch (HousekeepingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.hkscreen.updated', ['count' => $count]));
    }

    public function outage(Request $request): RedirectResponse
    {
        $property = app(Property::class);
        $bd = CarbonImmutable::parse($property->current_business_date);
        foreach (['date_from', 'date_to'] as $f) {
            $request->merge([$f => BusinessDateInput::parse($request->input($f), $bd)?->toDateString() ?? $request->input($f)]);
        }
        $data = $request->validate([
            'rooms' => ['required', 'array', 'min:1'], 'rooms.*' => ['integer'], 'type' => ['required', Rule::in(['OOO', 'OOS'])],
            'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after:date_from'],
            'reason_code_id' => ['required', 'integer', Rule::exists('pms_reason_codes', 'id')->where('property_id', $property->id)],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['rooms.required' => __('pms.hkscreen.select_rooms')], ['date_from' => __('pms.hkscreen.from'), 'date_to' => __('pms.hkscreen.to')]);
        try {
            DB::transaction(function () use ($data, $property, $request) {
                foreach ($data['rooms'] as $roomId) {
                    $this->hk->putOutOfOrder($property->id, (int) $roomId, $data['type'], $data['date_from'], $data['date_to'], (int) $data['reason_code_id'], $data['note'] ?? null, $request->user()->id);
                }
            });
        } catch (HousekeepingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.hkscreen.outage_saved', ['count' => count($data['rooms'])]));
    }

    public function release(Request $request, int $outage): RedirectResponse
    {
        try {
            $this->hk->release(app(Property::class)->id, $outage, $request->user()->id);
        } catch (HousekeepingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.hkscreen.released'));
    }
}
