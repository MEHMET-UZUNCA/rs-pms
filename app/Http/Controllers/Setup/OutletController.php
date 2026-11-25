<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Pos\Models\Outlet;
use App\Domain\Pos\Services\OutletException;
use App\Domain\Pos\Services\OutletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Kasa → Outlet'ler. Fidelio ayar listesi düzeni: Hızlı arama, "Pasifleri göster", sağda Yeni / Düzenle / Kopyala / Sil,
 * Yukarı / Aşağı. Düzenleme penceresinde outlet bilgileri ve outlet'e bağlı departman kodları.
 */
class OutletController extends Controller
{
    public function __construct(private readonly OutletService $outlets) {}

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $search = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('pasif');

        $outlets = Outlet::where('property_id', $property->id)
            ->with('defaultTransactionCode:id,code,name')->withCount('transactionCodes')
            ->when(! $showInactive, fn ($q) => $q->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('sort')->orderBy('id')->get();

        return view('setup.outlets.index', [
            'outlets' => $outlets, 'search' => $search, 'showInactive' => $showInactive,
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Outlet(['type' => 'restaurant', 'source_system' => 'simphony', 'is_active' => true]), []);
    }

    public function edit(Outlet $outlet): View
    {
        $this->authorizeProperty($outlet);

        return $this->form($outlet, $outlet->transactionCodes()->pluck('id')->all());
    }

    /** Fidelio "Copy": aynı bilgilerle yeni outlet; kodlar başka outlet'e bağlı olduğundan kopyalanmaz. */
    public function copy(Outlet $outlet): View
    {
        $this->authorizeProperty($outlet);
        $copy = $outlet->replicate(['simphony_rvc_number', 'default_transaction_code_id', 'sort']);
        $copy->code = '';
        $copy->name = $outlet->name.' '.__('pms.outlet.copy_suffix');

        return $this->form($copy, [], $outlet);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $this->authorizeProperty($outlet);

        return $this->save($request, $outlet);
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        $this->authorizeProperty($outlet);
        try {
            $this->outlets->delete($outlet);
        } catch (OutletException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $outlet->id);
        }

        return redirect()->route('setup.outlets.index')->with('status', __('pms.common.deleted', ['name' => $outlet->name]));
    }

    public function move(Outlet $outlet, string $direction): RedirectResponse
    {
        $this->authorizeProperty($outlet);
        $this->outlets->move($outlet, $direction);

        return back()->with('selected', $outlet->id);
    }

    private function save(Request $request, ?Outlet $outlet): RedirectResponse
    {
        $property = app(Property::class);
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('pms_outlets', 'code')->where('property_id', $property->id)->ignore($outlet?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(Outlet::TYPES))],
            'source_system' => ['required', Rule::in(array_keys(Outlet::SOURCES))],
            'simphony_rvc_number' => ['nullable', 'integer', 'min:1', 'max:99999',
                Rule::unique('pms_outlets', 'simphony_rvc_number')->where('property_id', $property->id)->ignore($outlet?->id)],
            'rsone_department_id' => ['nullable', 'integer', 'min:1'],
            'default_transaction_code_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'codes' => ['array'],
            'codes.*' => ['integer'],
        ], [
            'code.regex' => __('pms.validation.outlet_code_regex'),
            'code.unique' => __('pms.validation.outlet_code_unique'),
            'simphony_rvc_number.unique' => __('pms.validation.rvc_unique'),
        ], [
            'code' => __('pms.common.code'), 'name' => __('pms.common.name'), 'type' => __('pms.common.type'), 'source_system' => __('pms.outlet.source'),
            'simphony_rvc_number' => __('pms.outlet.rvc_number'), 'rsone_department_id' => __('pms.outlet.rsone_department'),
            'default_transaction_code_id' => __('pms.outlet.default_code'),
        ]);
        $codes = $data['codes'] ?? [];
        unset($data['codes']);
        $data['is_active'] = $request->boolean('is_active');

        try {
            $saved = $this->outlets->save($property->id, $data, $codes, $outlet);
        } catch (OutletException $e) {
            return back()->withInput()->withErrors(['codes' => $e->getMessage()]);
        }

        return redirect()->route('setup.outlets.index', ['pasif' => $saved->is_active ? null : 1])
            ->with('status', __('pms.common.saved', ['name' => $saved->name]))->with('selected', $saved->id);
    }

    /** @param array<int> $assigned */
    private function form(Outlet $outlet, array $assigned, ?Outlet $copiedFrom = null): View
    {
        $property = app(Property::class);
        $codes = DB::table('pms_transaction_codes as tc')
            ->leftJoin('pms_transaction_code_groups as g', 'g.id', '=', 'tc.group_id')
            ->leftJoin('pms_outlets as o', 'o.id', '=', 'tc.outlet_id')
            ->where('tc.property_id', $property->id)->where('tc.type', '!=', 'payment')->whereNull('tc.deleted_at')
            ->orderByRaw('CAST(tc.code AS UNSIGNED)')
            ->get(['tc.id', 'tc.code', 'tc.name', 'tc.category', 'tc.is_discount', 'tc.is_active', 'g.code as group_code', 'tc.outlet_id', 'o.name as outlet_name']);

        return view('setup.outlets.form', [
            'outlet' => $outlet, 'codes' => $codes, 'copiedFrom' => $copiedFrom,
            'assigned' => array_map('intval', old('codes', $assigned)),
        ]);
    }

    private function authorizeProperty(Outlet $outlet): void
    {
        abort_unless((int) $outlet->property_id === (int) app(Property::class)->id, 404);
    }
}
