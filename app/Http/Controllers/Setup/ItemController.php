<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ayarlar → Envanter kalemleri (Fidelio Items): bebek yatağı, ek yatak, ütü … toplam adet; rezervasyon kartının Inventory sekmesi kullanır. */
class ItemController extends Controller
{
    public function index(): View
    {
        $p = app(Property::class)->id;

        return view('setup.items', [
            'items' => DB::table('pms_items')->where('property_id', $p)->orderBy('code')->get(),
            'inUse' => DB::table('pms_reservation_items as ri')->join('pms_reservations as r', 'r.id', '=', 'ri.reservation_id')
                ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->groupBy('ri.item_id')->selectRaw('ri.item_id, COUNT(*) AS n')->pluck('n', 'item_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = app(Property::class)->id;
        $data = $this->validated($request, $p, null);
        DB::table('pms_items')->insert($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $p = app(Property::class)->id;
        abort_unless(DB::table('pms_items')->where('id', $item)->where('property_id', $p)->exists(), 404);
        $data = $this->validated($request, $p, $item);
        DB::table('pms_items')->where('id', $item)->update($data + ['updated_at' => now()]);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function destroy(int $item): RedirectResponse
    {
        $p = app(Property::class)->id;
        abort_unless(DB::table('pms_items')->where('id', $item)->where('property_id', $p)->exists(), 404);
        if (DB::table('pms_reservation_items')->where('item_id', $item)->exists()) {
            return back()->with('error', __('pms.items.in_use'));
        }
        DB::table('pms_items')->where('id', $item)->delete();

        return back()->with('status', __('pms.items.deleted'));
    }

    private function validated(Request $request, int $p, ?int $id): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code'))), 'price' => str_replace(',', '.', (string) $request->input('price')) ?: null]);

        return $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('pms_items', 'code')->where('property_id', $p)->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'quantity_total' => ['required', 'integer', 'between:1,999'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [], ['code' => __('pms.common.code'), 'name' => __('pms.common.name'), 'quantity_total' => __('pms.items.total')]);
    }
}
