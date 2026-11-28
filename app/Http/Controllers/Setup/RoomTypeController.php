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
 * Ayarlar → Oda tipleri (Fidelio Room Types): kod, ad, kişi sınırları, yatak, sanal (pseudo), web'de satış, sıra.
 * Odası, rezervasyonu ya da fiyatı olan tip silinmez; pasif yapılır.
 */
class RoomTypeController extends Controller
{
    public function index(): View
    {
        $p = app(Property::class)->id;

        return view('setup.room-types', [
            'types' => DB::table('pms_room_types')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->orderBy('code')->get(),
            'rooms' => DB::table('pms_rooms')->where('property_id', $p)->whereNull('deleted_at')->where('is_active', true)->groupBy('room_type_id')
                ->selectRaw('room_type_id, COUNT(*) AS n')->pluck('n', 'room_type_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = app(Property::class)->id;
        $data = $this->validated($request, $p, null);
        $id = DB::table('pms_room_types')->insertGetId($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);
        $this->rebuild($p, $id);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function update(Request $request, int $type): RedirectResponse
    {
        $p = app(Property::class)->id;
        abort_unless(DB::table('pms_room_types')->where('id', $type)->where('property_id', $p)->exists(), 404);
        $data = $this->validated($request, $p, $type);
        DB::table('pms_room_types')->where('id', $type)->update($data + ['updated_at' => now()]);
        $this->rebuild($p, $type);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function destroy(int $type): RedirectResponse
    {
        $p = app(Property::class)->id;
        $row = DB::table('pms_room_types')->where('id', $type)->where('property_id', $p)->first();
        abort_unless($row, 404);
        $used = DB::table('pms_rooms')->where('room_type_id', $type)->exists()
            || DB::table('pms_reservations')->where('room_type_id', $type)->orWhere('charge_room_type_id', $type)->exists()
            || DB::table('pms_reservation_nights')->where('room_type_id', $type)->exists()
            || DB::table('pms_rate_amounts')->where('room_type_id', $type)->exists()
            || DB::table('pms_block_inventory')->where('room_type_id', $type)->exists();
        if ($used) {
            return back()->with('error', __('pms.rtypes.in_use', ['code' => $row->code]));
        }
        DB::table('pms_inventory_daily')->where('room_type_id', $type)->delete();
        DB::table('pms_room_types')->where('id', $type)->delete();

        return back()->with('status', __('pms.rtypes.deleted', ['code' => $row->code]));
    }

    /** Müsaitlik tablosu: bugünden bir yıl ileri. */
    private function rebuild(int $propertyId, int $typeId): void
    {
        $bd = Property::query()->findOrFail($propertyId)->current_business_date->toImmutable();
        app(AvailabilityService::class)->rebuild($propertyId, $bd, $bd->addDays(365), $typeId);
    }

    private function validated(Request $request, int $p, ?int $id): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9._\-]+$/', Rule::unique('pms_room_types', 'code')->where('property_id', $p)->whereNull('deleted_at')->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'short_name' => ['nullable', 'string', 'max:30'],
            'default_adults' => ['required', 'integer', 'between:1,10'], 'max_adults' => ['required', 'integer', 'between:1,20', 'gte:default_adults'],
            'max_children' => ['required', 'integer', 'between:0,10'], 'max_occupancy' => ['required', 'integer', 'between:1,30', 'gte:max_adults'],
            'beds' => ['required', 'integer', 'between:0,20'], 'sort' => ['nullable', 'integer', 'between:0,9999'],
        ], [], ['code' => __('pms.common.code'), 'name' => __('pms.common.name'), 'default_adults' => __('pms.rtypes.default_adults'),
            'max_adults' => __('pms.rtypes.max_adults'), 'max_children' => __('pms.rtypes.max_children'), 'max_occupancy' => __('pms.rtypes.max_occupancy')]);

        return $data + ['sort' => (int) ($data['sort'] ?? 0), 'is_pseudo' => $request->boolean('is_pseudo'),
            'is_bookable_online' => $request->boolean('is_bookable_online'), 'is_active' => $request->boolean('is_active')];
    }
}
