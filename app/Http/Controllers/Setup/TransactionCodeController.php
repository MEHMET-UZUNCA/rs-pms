<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Pos\Models\Outlet;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Rates\Services\TransactionCodeException;
use App\Domain\Rates\Services\TransactionCodeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Kasa → Departman Kodları (Fidelio "Department Codes"). Liste Fidelio'daki sütunlarla (No, Code, Description, Manual, Group,
 * Unit, Inactive); düzenleme penceresi Fidelio'daki alan sırasıyla, tarihli vergi listesiyle.
 */
class TransactionCodeController extends Controller
{
    public const TYPES = [
        'revenue' => 'Debit — gelir', 'payment' => 'Credit — ödeme', 'paid_out' => 'Paid-out', 'deposit' => 'Depozito',
        'ar_transfer' => 'Cari aktarım', 'internal' => 'İç / sistem',
    ];

    public const PAYMENT_KINDS = [
        'cash' => 'Nakit', 'card' => 'Kredi kartı', 'bank_transfer' => 'Havale / EFT', 'city_ledger' => 'Cari (City Ledger)',
        'foreign_cash' => 'Döviz nakit', 'cheque' => 'Çek', 'voucher' => 'Voucher',
    ];

    public const BUCKETS = [
        'room' => 'Oda (Logis)', 'breakfast' => 'Kahvaltı', 'lunch' => 'Öğle yemeği', 'dinner' => 'Akşam yemeği',
        'allinc_food' => 'Her şey dahil yiyecek', 'allinc_beverage' => 'Her şey dahil içecek', 'food' => 'Yiyecek', 'beverage' => 'İçecek',
        'minibar' => 'Minibar', 'telephone' => 'Telefon', 'spa' => 'Spa', 'laundry' => 'Çamaşırhane', 'other' => 'Diğer',
    ];

    public function __construct(private readonly TransactionCodeService $codes) {}

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $search = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('pasif');
        $groupId = (int) $request->query('grup', 0);
        $today = $property->current_business_date?->toDateString() ?? now()->toDateString();

        $codes = TransactionCode::where('pms_transaction_codes.property_id', $property->id)
            ->with(['group:id,code,name', 'outlet:id,code,name'])
            ->when(! $showInactive, fn ($q) => $q->where('is_active', true))
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "{$search}%")->orWhere('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "{$search}%")))
            ->orderBy('sort')->orderByRaw('CAST(code AS UNSIGNED)')->get();

        $taxes = DB::table('pms_transaction_code_taxes as l')->join('pms_taxes as t', 't.id', '=', 'l.tax_id')
            ->whereIn('l.transaction_code_id', $codes->pluck('id'))
            ->where(fn ($q) => $q->whereNull('l.valid_from')->orWhere('l.valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('l.valid_to')->orWhere('l.valid_to', '>=', $today))
            ->get(['l.transaction_code_id', 't.rate'])->groupBy('transaction_code_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => '%'.rtrim(rtrim(number_format((float) $r->rate, 2, ',', ''), '0'), ','))->implode(' + '));

        return view('setup.transaction-codes.index', [
            'codes' => $codes, 'taxes' => $taxes, 'search' => $search, 'showInactive' => $showInactive, 'groupId' => $groupId,
            'groups' => DB::table('pms_transaction_code_groups')->where('property_id', $property->id)->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    public function create(): View
    {
        return $this->form(new TransactionCode(['type' => 'revenue', 'is_active' => true, 'manual_post_allowed' => true, 'is_tax_inclusive' => true]), []);
    }

    public function edit(TransactionCode $code): View
    {
        $this->authorizeProperty($code);

        return $this->form($code, $this->taxRows($code));
    }

    public function copy(TransactionCode $code): View
    {
        $this->authorizeProperty($code);
        $copy = $code->replicate(['sort', 'legacy_id', 'outlet_id']);
        $copy->code = '';

        return $this->form($copy, $this->taxRows($code), $code);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, TransactionCode $code): RedirectResponse
    {
        $this->authorizeProperty($code);

        return $this->save($request, $code);
    }

    public function destroy(TransactionCode $code): RedirectResponse
    {
        $this->authorizeProperty($code);
        try {
            $this->codes->delete($code);
        } catch (TransactionCodeException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $code->id);
        }

        return redirect()->route('setup.transaction-codes.index')->with('status', "{$code->code} {$code->name} silindi.");
    }

    public function move(TransactionCode $code, string $direction): RedirectResponse
    {
        $this->authorizeProperty($code);
        $this->codes->move($code, $direction);

        return back()->with('selected', $code->id);
    }

    private function save(Request $request, ?TransactionCode $code): RedirectResponse
    {
        $property = app(Property::class);
        $request->merge(['category' => mb_strtoupper(trim((string) $request->input('category'))) ?: null]);
        $codeRef = fn () => Rule::exists('pms_transaction_codes', 'id')->where('property_id', $property->id);
        $data = $request->validate([
            'code' => ['required', 'regex:/^[0-9]{1,6}$/', Rule::unique('pms_transaction_codes', 'code')->where('property_id', $property->id)->ignore($code?->id)],
            'category' => ['nullable', 'string', 'max:5'],
            'name' => ['required', 'string', 'max:100'],
            'folio_text' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'group_id' => ['required', 'integer'],
            'arrangement_code' => ['nullable', 'string', 'max:10'],
            'government_code' => ['nullable', 'string', 'max:20'],
            'return_transaction_code_id' => ['nullable', 'integer', $codeRef()],
            'unit_description' => ['nullable', 'string', 'max:50'],
            'default_price' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'physical_origin' => ['nullable', 'string', 'max:30'],
            'payment_kind' => ['nullable', 'required_if:type,payment', Rule::in(array_keys(self::PAYMENT_KINDS))],
            'currency_code' => ['nullable', Rule::exists('pms_currencies', 'code')],
            'cc_commission_transaction_code_id' => ['nullable', 'integer', $codeRef()],
            'cc_commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_bucket' => ['nullable', Rule::in(array_keys(self::BUCKETS))],
            'food_beverage' => ['nullable', Rule::in(['food', 'beverage', 'other'])],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'folio_subfunction' => ['nullable', 'string', 'max:30'],
            'adjustment_transaction_code_id' => ['nullable', 'integer', $codeRef()],
            'discount_of_transaction_code_id' => ['nullable', 'integer', $codeRef()],
            'outlet_id' => ['nullable', 'integer', Rule::exists('pms_outlets', 'id')->where('property_id', $property->id)],
            'taxes' => ['array'],
            'taxes.*.tax_id' => ['nullable', 'integer'],
            'taxes.*.valid_from' => ['nullable', 'date'],
            'taxes.*.valid_to' => ['nullable', 'date'],
        ], [
            'code.regex' => 'Numara yalnız rakamlardan oluşur (en çok 6 hane).',
            'code.unique' => 'Bu numarayla bir departman kodu zaten var.',
            'payment_kind.required_if' => 'Ödeme kodu için ödeme türü seçilmeli.',
        ], [
            'code' => 'Numara', 'category' => 'Kısa kod', 'name' => 'Açıklama', 'folio_text' => 'Folyo metni', 'type' => 'Tip', 'group_id' => 'Grup',
            'default_price' => 'Varsayılan fiyat', 'cc_commission_pct' => 'Komisyon yüzdesi', 'credit_limit' => 'Kredi limiti',
        ]);
        foreach (['is_active', 'manual_post_allowed', 'is_discount', 'is_no_show', 'is_tax_inclusive', 'disabled_in_prearrival', 'disabled_in_billing',
            'disabled_in_ar', 'disabled_in_payments', 'direct_debit', 'non_hotel_revenue', 'open_cash_drawer', 'hostess_enabled', 'post_tax_only'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if ($data['type'] !== 'payment') {
            $data['payment_kind'] = null;
        }
        $taxes = $data['taxes'] ?? [];
        unset($data['taxes']);

        try {
            $saved = $this->codes->save($property->id, $data, $taxes, $code);
        } catch (TransactionCodeException $e) {
            return back()->withInput()->withErrors(['taxes' => $e->getMessage()]);
        }

        return redirect()->route('setup.transaction-codes.index', ['pasif' => $saved->is_active ? null : 1])
            ->with('status', "{$saved->code} {$saved->name} kaydedildi.")->with('selected', $saved->id);
    }

    /** @return array<int, array{tax_id:int, valid_from:?string, valid_to:?string}> */
    private function taxRows(TransactionCode $code): array
    {
        return DB::table('pms_transaction_code_taxes')->where('transaction_code_id', $code->id)->orderBy('sequence')
            ->get(['tax_id', 'valid_from', 'valid_to'])
            ->map(fn ($r) => ['tax_id' => (int) $r->tax_id, 'valid_from' => $r->valid_from ? substr($r->valid_from, 0, 10) : null,
                'valid_to' => $r->valid_to ? substr($r->valid_to, 0, 10) : null])->all();
    }

    private function form(TransactionCode $code, array $taxRows, ?TransactionCode $copiedFrom = null): View
    {
        $property = app(Property::class);

        return view('setup.transaction-codes.form', [
            'code' => $code, 'copiedFrom' => $copiedFrom,
            'taxRows' => old('taxes', $taxRows),
            'groups' => DB::table('pms_transaction_code_groups')->where('property_id', $property->id)->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'allCodes' => DB::table('pms_transaction_codes')->where('property_id', $property->id)->whereNull('deleted_at')
                ->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'taxes' => DB::table('pms_taxes')->where('property_id', $property->id)->orderBy('rate')->orderBy('valid_from')
                ->get(['id', 'code', 'name', 'rate', 'valid_from', 'valid_to']),
            'currencies' => DB::table('pms_currencies')->where('is_active', true)->orderBy('sort')->get(['code', 'name']),
            'outlets' => Outlet::where('property_id', $property->id)->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    private function authorizeProperty(TransactionCode $code): void
    {
        abort_unless((int) $code->property_id === (int) app(Property::class)->id, 404);
    }
}
