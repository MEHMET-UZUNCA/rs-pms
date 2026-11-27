<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Block;
use App\Domain\Reservations\Services\BlockService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reservation → Block / Group Reservation: blok listesi, blok kartı, kota ve üyeler, kotadan rezervasyon, toplu işlemler. */
class BlockController extends Controller
{
    public function __construct(private readonly BlockService $blocks) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $status = in_array($request->query('durum'), ['active', 'cancelled', 'past'], true) ? $request->query('durum') : 'active';
        $q = trim((string) $request->query('ara', ''));
        $bd = $p->current_business_date->toDateString();

        $rows = DB::table('pms_blocks as b')->leftJoin('pms_profiles as a', 'a.id', '=', 'b.profile_id')->leftJoin('pms_rate_codes as rc', 'rc.id', '=', 'b.rate_code_id')
            ->where('b.property_id', $p->id)->whereNull('b.deleted_at')
            ->when($status === 'active', fn ($w) => $w->where('b.status', '!=', 'cancelled')->where('b.end_date', '>=', $bd))
            ->when($status === 'past', fn ($w) => $w->where('b.status', '!=', 'cancelled')->where('b.end_date', '<', $bd))
            ->when($status === 'cancelled', fn ($w) => $w->where('b.status', 'cancelled'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('b.code', 'like', "%{$q}%")->orWhere('b.name', 'like', "%{$q}%")->orWhere('a.display_name', 'like', "%{$q}%")))
            ->orderBy('b.start_date')->limit(300)
            ->get(['b.*', 'a.display_name as agent', 'rc.code as rate_code']);
        $counts = DB::table('pms_block_inventory')->whereIn('block_id', $rows->pluck('id'))->groupBy('block_id', 'date')
            ->selectRaw('block_id, SUM(allotted) AS a, SUM(picked_up) AS p')->get()->groupBy('block_id')
            ->map(fn ($days) => ['allotted' => (int) $days->max('a'), 'picked_up' => (int) $days->max('p')]);

        return view('blocks.index', ['rows' => $rows, 'counts' => $counts, 'status' => $status, 'q' => $q, 'selected' => (int) session('selected', 0)]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('tur') === 'allotment' ? 'allotment' : 'group';
        $bd = app(Property::class)->current_business_date;

        return $this->form(new Block(['type' => $type, 'status' => 'tentative', 'start_date' => $bd, 'end_date' => $bd->copy()->addDays(3)]), []);
    }

    public function edit(Block $block): View
    {
        $this->own($block);

        return $this->form($block, DB::table('pms_block_inventory')->where('block_id', $block->id)->groupBy('room_type_id')
            ->selectRaw('room_type_id, MAX(allotted) AS n')->pluck('n', 'room_type_id')->map(fn ($n) => (int) $n)->all());
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, Block $block): RedirectResponse
    {
        $this->own($block);

        return $this->save($request, $block);
    }

    public function show(Block $block): View
    {
        $this->own($block);
        $members = DB::table('pms_reservations as r')->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')->leftJoin('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->where('r.block_id', $block->id)->orderBy('t.sort')->orderBy('rm.number')->orderBy('r.id')
            ->get(['r.id', 'r.confirmation_number', 'r.status', 'r.arrival_date', 'r.departure_date', 'r.adults', 'r.children', 'g.display_name', 'rm.number as room', 'rm.hk_status', 't.code as room_type']);
        $master = $this->blocks->master($block);
        $routed = $master ? DB::table('pms_routing_rules')->where('target_folio_id', $master->id)->where('is_active', true)->pluck('source_reservation_id')->flip() : collect();

        return view('blocks.show', [
            'block' => $block, 'summary' => $this->blocks->summary($block), 'members' => $members, 'master' => $master, 'routed' => $routed,
            'agent' => $block->profile_id ? DB::table('pms_profiles')->where('id', $block->profile_id)->value('display_name') : null,
            'rate' => DB::table('pms_rate_codes')->where('id', $block->rate_code_id)->first(['code', 'name']),
            'types' => DB::table('pms_reservation_types')->where('property_id', $block->property_id)->whereNull('deleted_at')->orderBy('code')->get(['id', 'code', 'name']),
            'bd' => app(Property::class)->current_business_date,
        ]);
    }

    /** Kotadan rezervasyon (rooming list). */
    public function pickUp(Request $request, Block $block): RedirectResponse
    {
        $this->own($block);
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        foreach (['arrival_date', 'departure_date'] as $f) {
            $request->merge([$f => BusinessDateInput::parse((string) $request->input($f), $bd)?->toDateString() ?? $request->input($f)]);
        }
        $data = $request->validate([
            'room_type_id' => ['required', 'integer', Rule::exists('pms_block_inventory', 'room_type_id')->where('block_id', $block->id)],
            'count' => ['nullable', 'integer', 'min:0', 'max:200'], 'names' => ['nullable', 'string', 'max:20000'],
            'adults' => ['required', 'integer', 'min:1', 'max:6'],
            'reservation_type_id' => ['required', 'integer', Rule::exists('pms_reservation_types', 'id')->where('property_id', $p->id)],
            'arrival_date' => ['required', 'date'], 'departure_date' => ['required', 'date', 'after:arrival_date'],
        ]);
        try {
            $created = $this->blocks->pickUp($block, (int) $data['room_type_id'], (int) ($data['count'] ?? 0), preg_split('/\R/', (string) ($data['names'] ?? '')), $data, $request->user()->id);
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.block.picked', ['count' => count($created)]));
    }

    /** Toplu işlemler: oda ata, giriş, ana hesaba yönlendir / kaldır. */
    public function bulk(Request $request, Block $block): RedirectResponse
    {
        $this->own($block);
        $data = $request->validate(['action' => ['required', Rule::in(['assign', 'check_in', 'route', 'unroute'])], 'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']],
            ['ids.required' => __('pms.block.select_members')]);
        $ids = array_map('intval', $data['ids']);
        $user = $request->user()->id;
        switch ($data['action']) {
            case 'assign':
                $r = $this->blocks->autoAssign($block, $ids, $user);

                $back = back()->with('status', __('pms.block.assigned', ['count' => $r['assigned']]));

                return $r['skipped'] ? $back->with('error', __('pms.block.no_room_for', ['list' => implode(', ', $r['skipped'])])) : $back;
            case 'check_in':
                abort_unless(Gate::allows('permission', 'reservations.checkin'), 403);
                $r = $this->blocks->checkInMany($block, $ids, $user);

                $back = back()->with('status', __('pms.block.checked_in', ['count' => $r['checked_in']]));

                return $r['errors'] ? $back->with('error', implode(' · ', $r['errors'])) : $back;
            case 'route':
                $master = $this->blocks->routeToMaster($block, $ids, $user);

                return back()->with('status', __('pms.block.routed', ['count' => count($ids), 'folio' => $master->folio_number]));
            default:
                return back()->with('status', __('pms.block.unrouted', ['count' => $this->blocks->removeRouting($block, $ids)]));
        }
    }

    private function save(Request $request, ?Block $block): RedirectResponse
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        foreach (['start_date', 'end_date', 'cutoff_date'] as $f) {
            $raw = trim((string) $request->input($f));
            $request->merge([$f => $raw === '' ? null : (BusinessDateInput::parse($raw, $bd)?->toDateString() ?? 'x')]);
        }
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $p->id);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9._\-]+$/', Rule::unique('pms_blocks', 'code')->where('property_id', $p->id)->ignore($block?->id)],
            'name' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(BlockService::TYPES)], 'status' => ['required', Rule::in(BlockService::STATUSES)],
            'profile_id' => ['required', 'integer', $own('pms_profiles')],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'], 'cutoff_date' => ['nullable', 'date'],
            'rate_code_id' => ['required', 'integer', $own('pms_rate_codes')], 'market_segment_id' => ['required', 'integer', $own('pms_market_segments')],
            'source_code_id' => ['required', 'integer', $own('pms_source_codes')], 'contract_number' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string', 'max:2000'],
            'rooms' => ['nullable', 'array'], 'rooms.*' => ['nullable', 'integer', 'min:0', 'max:500'],
        ], [], ['code' => __('pms.common.code'), 'name' => __('pms.common.name'), 'profile_id' => __('pms.block.agent'), 'end_date' => __('pms.block.end')]);
        $rooms = array_map('intval', array_filter($data['rooms'] ?? [], fn ($n) => (int) $n > 0));
        $validTypes = DB::table('pms_room_types')->where('property_id', $p->id)->pluck('id')->all();
        $rooms = array_intersect_key($rooms, array_flip($validTypes));
        unset($data['rooms']);
        try {
            $saved = $this->blocks->save($p->id, $data, $rooms, $block, $request->user()->id);
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('blocks.show', $saved)->with('status', __('pms.common.saved', ['name' => $saved->code]));
    }

    private function form(Block $block, array $rooms): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table, string $order = 'code') => DB::table($table)->where('property_id', $p)->whereNull('deleted_at')->orderBy($order)->get(['id', 'code', 'name']);

        return view('blocks.form', [
            'block' => $block, 'rooms' => $rooms,
            'roomTypes' => DB::table('pms_room_types')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->get(['id', 'code', 'name']),
            'firms' => DB::table('pms_profiles')->where('property_id', $p)->whereIn('type', ['travel_agent', 'company', 'source'])->whereNull('deleted_at')
                ->where('status', '!=', 'inactive')->orderBy('display_name')->get(['id', 'display_name', 'type']),
            'rates' => $list('pms_rate_codes'), 'segments' => $list('pms_market_segments', 'sort'), 'sources' => $list('pms_source_codes', 'sort'),
        ]);
    }

    private function own(Block $block): void
    {
        abort_unless((int) $block->property_id === (int) app(Property::class)->id, 404);
    }
}
