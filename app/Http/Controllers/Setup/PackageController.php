<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Paketler (Fidelio Packages / Package Link Header): pansiyon (board) ya da ek paket; içerdiği öğünler (mutfak öğün sayıları),
 * kayıt sıklığı, fiyata dahil mi; elemanları: departman kodu (110 kahvaltı, 130 akşam yemeği …), hesaplama (oda / kişi / yetişkin / çocuk),
 * yetişkin ve çocuk tutarı. Oda geliri = gece fiyatı − paket elemanları. Fiyat koduna ya da geceye bağlı paket silinmez.
 */
class PackageController extends Controller
{
    public const TYPES = ['board', 'addon'];

    public const RHYTHMS = ['every_night', 'arrival', 'departure', 'once'];

    public const CALCS = ['per_person', 'per_adult', 'per_child', 'per_room'];

    public function index(Request $request): View
    {
        $p = app(Property::class)->id;
        $packages = DB::table('pms_packages')->where('property_id', $p)->whereNull('deleted_at')->orderBy('type')->orderBy('code')->get();
        $selected = $packages->firstWhere('id', (int) $request->query('paket', session('package', 0))) ?? $packages->first();

        return view('setup.catalog.packages', [
            'packages' => $packages, 'selected' => $selected,
            'elements' => $selected ? DB::table('pms_package_elements as e')->join('pms_transaction_codes as t', 't.id', '=', 'e.transaction_code_id')
                ->where('e.package_id', $selected->id)->orderBy('t.code')->get(['e.*', 't.code', 't.name']) : collect(),
            'codes' => DB::table('pms_transaction_codes')->where('property_id', $p)->whereNull('deleted_at')->where('type', 'revenue')->where('is_active', true)
                ->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'rates' => DB::table('pms_rate_codes')->where('property_id', $p)->whereNull('deleted_at')->whereNotNull('package_id')->groupBy('package_id')
                ->selectRaw('package_id, COUNT(*) AS n')->pluck('n', 'package_id'),
        ]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $p = app(Property::class)->id;
        $id && abort_unless(DB::table('pms_packages')->where('id', $id)->where('property_id', $p)->whereNull('deleted_at')->exists(), 404);
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('pms_packages', 'code')->where('property_id', $p)->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(self::TYPES)], 'posting_rhythm' => ['required', Rule::in(self::RHYTHMS)],
        ]);
        $data += ['is_included_in_rate' => $request->boolean('is_included_in_rate'), 'includes_breakfast' => $request->boolean('includes_breakfast'),
            'includes_lunch' => $request->boolean('includes_lunch'), 'includes_dinner' => $request->boolean('includes_dinner'), 'updated_at' => now()];
        if ($id) {
            DB::table('pms_packages')->where('id', $id)->update($data);
        } else {
            $id = DB::table('pms_packages')->insertGetId($data + ['property_id' => $p, 'created_at' => now()]);
        }

        return redirect()->route('setup.packages.index', ['paket' => $id])->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = DB::table('pms_packages')->where('id', $id)->where('property_id', app(Property::class)->id)->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        if (DB::table('pms_rate_codes')->where('package_id', $id)->exists() || DB::table('pms_reservation_nights')->where('package_id', $id)->exists()
            || DB::table('pms_reservation_packages')->where('package_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.in_use', ['code' => $row->code]));
        }
        DB::table('pms_package_elements')->where('package_id', $id)->delete();
        DB::table('pms_packages')->where('id', $id)->delete();

        return redirect()->route('setup.packages.index')->with('status', __('pms.catalog.deleted', ['code' => $row->code]));
    }

    /** Eleman ekle / güncelle ($element boşsa yeni). */
    public function saveElement(Request $request, int $id, ?int $element = null): RedirectResponse
    {
        $p = app(Property::class)->id;
        abort_unless(DB::table('pms_packages')->where('id', $id)->where('property_id', $p)->exists(), 404);
        foreach (['amount_adult', 'amount_child'] as $f) {
            $request->merge([$f => str_replace(',', '.', (string) $request->input($f)) ?: 0]);
        }
        $data = $request->validate([
            'transaction_code_id' => ['required', 'integer', Rule::exists('pms_transaction_codes', 'id')->where('property_id', $p)->where('type', 'revenue')],
            'calc' => ['required', Rule::in(self::CALCS)], 'amount_adult' => ['required', 'numeric', 'between:0,999999'], 'amount_child' => ['required', 'numeric', 'between:0,999999'],
        ], [], ['transaction_code_id' => __('pms.catalog.department_code')]);
        $data['is_allowance'] = $request->boolean('is_allowance');
        $element ? DB::table('pms_package_elements')->where('id', $element)->where('package_id', $id)->update($data)
            : DB::table('pms_package_elements')->insert($data + ['package_id' => $id]);

        return redirect()->route('setup.packages.index', ['paket' => $id])->with('status', __('pms.catalog.element_saved'));
    }

    public function deleteElement(int $id, int $element): RedirectResponse
    {
        abort_unless(DB::table('pms_packages')->where('id', $id)->where('property_id', app(Property::class)->id)->exists(), 404);
        DB::table('pms_package_elements')->where('id', $element)->where('package_id', $id)->delete();

        return redirect()->route('setup.packages.index', ['paket' => $id])->with('status', __('pms.catalog.element_deleted'));
    }
}
