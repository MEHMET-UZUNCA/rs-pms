<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Odalar (Fidelio Rooms): numara, tip, blok, kat, kat hizmetleri bölümü, kişi, sigara, engelli, sanal oda, özellikler, sıra.
 * Geçmişi olan oda silinmez, pasif yapılır (müsaitlikten düşer). Tip ya da durum değişince müsaitlik bir yıl ileriye yeniden hesaplanır.
 */
class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $p = app(Property::class)->id;
        $type = (int) $request->query('tip');
        $floor = (int) $request->query('kat');
        $inactive = $request->boolean('pasif');
        $rooms = DB::table('pms_rooms as r')->join('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->leftJoin('pms_floors as f', 'f.id', '=', 'r.floor_id')->leftJoin('pms_buildings as b', 'b.id', '=', 'r.building_id')
            ->where('r.property_id', $p)->whereNull('r.deleted_at')->when(! $inactive, fn ($q) => $q->where('r.is_active', true))
            ->when($type, fn ($q) => $q->where('r.room_type_id', $type))->when($floor, fn ($q) => $q->where('r.floor_id', $floor))
            ->orderBy('r.sort')->orderBy('r.number')
            ->get(['r.*', 't.code as type_code', 'f.name as floor_name', 'b.name as building_name']);
        $features = DB::table('pms_room_feature_room as x')->join('pms_room_features as f', 'f.id', '=', 'x.room_feature_id')
            ->whereIn('x.room_id', $rooms->pluck('id'))->orderBy('f.code')->get(['x.room_id', 'f.code'])->groupBy('room_id');

        return view('setup.rooms.index', $this->lists($p) + ['rooms' => $rooms, 'features' => $features, 'type' => $type, 'floor' => $floor, 'inactive' => $inactive]);
    }

    public function create(): View
    {
        return view('setup.rooms.form', $this->lists(app(Property::class)->id) + ['room' => null, 'selected' => []]);
    }

    public function edit(int $room): View
    {
        $p = app(Property::class)->id;
        $row = DB::table('pms_rooms')->where('id', $room)->where('property_id', $p)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        return view('setup.rooms.form', $this->lists($p) + ['room' => $row,
            'selected' => DB::table('pms_room_feature_room')->where('room_id', $room)->pluck('room_feature_id')->map(fn ($i) => (int) $i)->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = app(Property::class)->id;
        [$data, $features] = $this->validated($request, $p, null);
        $id = DB::transaction(function () use ($data, $features, $p) {
            $id = DB::table('pms_rooms')->insertGetId($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);
            $this->syncFeatures($id, $features);

            return $id;
        });
        $this->rebuild($p, [(int) $data['room_type_id']]);

        return redirect()->route('setup.rooms.index', ['tip' => $data['room_type_id']])->with('status', __('pms.common.saved', ['name' => $data['number']]))->with('selected', $id);
    }

    public function update(Request $request, int $room): RedirectResponse
    {
        $p = app(Property::class)->id;
        $old = DB::table('pms_rooms')->where('id', $room)->where('property_id', $p)->whereNull('deleted_at')->first();
        abort_unless($old, 404);
        [$data, $features] = $this->validated($request, $p, $room);
        if ((int) $old->room_type_id !== (int) $data['room_type_id'] || ! $data['is_active']) {
            // Gelecekte bu odaya atanmış rezervasyon varken tip değiştirilemez / pasif yapılamaz
            $future = DB::table('pms_reservations')->where('room_id', $room)->whereIn('status', ['confirmed', 'tentative', 'waitlist', 'in_house'])->pluck('confirmation_number');
            if ($future->isNotEmpty()) {
                return back()->withInput()->with('error', __('pms.rooms_setup.has_reservations', ['list' => $future->take(5)->implode(', ')]));
            }
        }
        DB::transaction(function () use ($room, $data, $features) {
            DB::table('pms_rooms')->where('id', $room)->update($data + ['updated_at' => now()]);
            $this->syncFeatures($room, $features);
        });
        $this->rebuild($p, array_unique([(int) $old->room_type_id, (int) $data['room_type_id']]));

        return redirect()->route('setup.rooms.index', ['tip' => $data['room_type_id']])->with('status', __('pms.common.saved', ['name' => $data['number']]))->with('selected', $room);
    }

    public function destroy(int $room): RedirectResponse
    {
        $p = app(Property::class)->id;
        $row = DB::table('pms_rooms')->where('id', $room)->where('property_id', $p)->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        $used = DB::table('pms_reservations')->where('room_id', $room)->exists() || DB::table('pms_reservation_nights')->where('room_id', $room)->exists()
            || DB::table('pms_postings')->where('room_id', $room)->exists() || DB::table('pms_room_outages')->where('room_id', $room)->exists();
        if ($used) {
            return back()->with('error', __('pms.rooms_setup.in_use', ['room' => $row->number]));
        }
        DB::table('pms_room_feature_room')->where('room_id', $room)->delete();
        DB::table('pms_rooms')->where('id', $room)->delete();
        $this->rebuild($p, [(int) $row->room_type_id]);

        return redirect()->route('setup.rooms.index')->with('status', __('pms.rooms_setup.deleted', ['room' => $row->number]));
    }

    /** @return array<string, mixed> seçim listeleri */
    private function lists(int $p): array
    {
        return [
            'types' => DB::table('pms_room_types')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->orderBy('code')->get(['id', 'code', 'name', 'max_occupancy', 'is_active']),
            'floors' => DB::table('pms_floors')->where('property_id', $p)->orderBy('sort')->get(['id', 'name', 'building_id']),
            'buildings' => DB::table('pms_buildings')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->get(['id', 'code', 'name']),
            'sections' => DB::table('pms_hk_sections')->where('property_id', $p)->orderBy('sort')->get(['id', 'code', 'name']),
            'allFeatures' => DB::table('pms_room_features')->where('property_id', $p)->orderBy('code')->get(['id', 'code', 'name']),
        ];
    }

    /** @param list<int> $typeIds */
    private function rebuild(int $propertyId, array $typeIds): void
    {
        $bd = Property::query()->findOrFail($propertyId)->current_business_date->toImmutable();
        foreach ($typeIds as $typeId) {
            app(AvailabilityService::class)->rebuild($propertyId, $bd, $bd->addDays(365), $typeId);
        }
    }

    /** @param list<int> $features */
    private function syncFeatures(int $roomId, array $features): void
    {
        DB::table('pms_room_feature_room')->where('room_id', $roomId)->delete();
        foreach (array_unique($features) as $f) {
            DB::table('pms_room_feature_room')->insert(['room_id' => $roomId, 'room_feature_id' => $f]);
        }
    }

    /** @return array{0: array<string, mixed>, 1: list<int>} */
    private function validated(Request $request, int $p, ?int $id): array
    {
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $p);
        $request->merge(['number' => mb_strtoupper(trim((string) $request->input('number')))]);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('pms_rooms', 'number')->where('property_id', $p)->whereNull('deleted_at')->ignore($id)],
            'name' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:500'],
            'room_type_id' => ['required', 'integer', $own('pms_room_types')],
            'building_id' => ['nullable', 'integer', $own('pms_buildings')], 'floor_id' => ['nullable', 'integer', $own('pms_floors')],
            'hk_section_id' => ['nullable', 'integer', $own('pms_hk_sections')],
            'max_occupancy' => ['nullable', 'integer', 'between:1,30'], 'hk_credits' => ['nullable', 'numeric', 'between:0,99'], 'sort' => ['nullable', 'integer', 'between:0,99999'],
            'features' => ['nullable', 'array'], 'features.*' => ['integer', $own('pms_room_features')],
        ], [], ['number' => __('pms.rooms_setup.number'), 'room_type_id' => __('pms.res.room_type')]);
        $features = array_map('intval', $data['features'] ?? []);
        unset($data['features']);

        return [array_merge($data, ['hk_credits' => $data['hk_credits'] ?? 1]) + ['sort' => (int) ($data['sort'] ?? 0), 'is_smoking' => $request->boolean('is_smoking'), 'is_accessible' => $request->boolean('is_accessible'),
            'is_pseudo' => $request->boolean('is_pseudo'), 'is_active' => $request->boolean('is_active')], $features];
    }
}
