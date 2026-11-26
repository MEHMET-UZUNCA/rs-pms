<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Models\RateAmount;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Services\RateCodeService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Ayarlar → Fiyat kodları (Fidelio Rates): kategori ağacında fiyat kodları ve seçili kodun özeti; Rate Edit penceresi
 * (Rate sekmesi = başlık, Rate Details = dönemler, Prices = fiyatlar, Package Link Header = pansiyon içeriği).
 */
class RateCodeController extends Controller
{
    public function __construct(private readonly RateCodeService $rates) {}

    public function index(Request $request): View
    {
        $p = app(Property::class)->id;
        $search = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('pasif');
        $webOnly = $request->boolean('web');
        $codes = RateCode::where('property_id', $p)
            ->when($webOnly, fn ($q) => $q->where('is_public', true))
            ->with(['package:id,code,name'])->withCount('amounts')
            ->when(! $showInactive, fn ($q) => $q->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('sort')->orderBy('code')->get();
        $categories = DB::table('pms_rate_categories')->where('property_id', $p)->orderBy('sort')->orderBy('name')->get(['id', 'code', 'name']);

        return view('setup.rates.index', [
            'groups' => $categories->map(fn ($c) => ['category' => $c, 'codes' => $codes->where('rate_category_id', $c->id)->values()])
                ->push(['category' => null, 'codes' => $codes->whereNull('rate_category_id')->values()])
                ->filter(fn ($g) => $g['codes']->isNotEmpty() || ($g['category'] && $search === '')),
            'search' => $search, 'showInactive' => $showInactive, 'webOnly' => $webOnly, 'count' => $codes->count(),
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    /** Sağdaki özet (Fidelio Rate header / Rate detail information). */
    public function summary(RateCode $rate): View
    {
        $this->own($rate);

        return view('setup.rates._summary', $this->details($rate) + ['rate' => $rate->load(['package.elements.transactionCode', 'roomTransactionCode'])]);
    }

    public function create(): View
    {
        return $this->form(new RateCode(['currency_code' => app(Property::class)->base_currency, 'is_active' => true, 'refresh_exchange_at_checkin' => true,
            'room_transaction_code_id' => DB::table('pms_transaction_codes')->where('property_id', app(Property::class)->id)->where('code', '100')->value('id')]));
    }

    public function edit(RateCode $rate): View
    {
        $this->own($rate);

        return $this->form($rate);
    }

    public function copy(Request $request, RateCode $rate): RedirectResponse
    {
        $this->own($rate);
        $p = app(Property::class)->id;
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => ['required', 'string', 'max:20', Rule::unique('pms_rate_codes', 'code')->where('property_id', $p)]],
            [], ['code' => __('pms.rates.code')]);
        $copy = $this->rates->copy($rate, $data['code'], $rate->name);

        return redirect()->route('setup.rates.edit', $copy)->with('status', __('pms.rates.copied', ['from' => $rate->code, 'to' => $copy->code]));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, RateCode $rate): RedirectResponse
    {
        $this->own($rate);

        return $this->save($request, $rate);
    }

    public function destroy(RateCode $rate): RedirectResponse
    {
        $this->own($rate);
        try {
            $this->rates->delete($rate);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $rate->id);
        }

        return redirect()->route('setup.rates.index')->with('status', __('pms.common.deleted', ['name' => $rate->code]));
    }

    // ------------------------------------------------------------------ fiyat detayları (Rate Details + Prices)

    public function detailCreate(RateCode $rate): View
    {
        $this->own($rate);

        return $this->detailForm($rate, new RateAmount(['weekdays' => 127, 'date_from' => $rate->valid_from, 'date_to' => $rate->valid_to]), []);
    }

    public function detailEdit(RateCode $rate, RateAmount $detail): View
    {
        $this->ownDetail($rate, $detail);

        return $this->detailForm($rate, $detail, $detail->roomTypes()->pluck('pms_room_types.id')->all());
    }

    /** Fidelio "Copy": aynı fiyatlarla yeni dönem (tarihleri değiştirilerek kaydedilir). */
    public function detailCopy(RateCode $rate, RateAmount $detail): View
    {
        $this->ownDetail($rate, $detail);

        return $this->detailForm($rate, $detail->replicate(), $detail->roomTypes()->pluck('pms_room_types.id')->all(), true);
    }

    public function detailStore(Request $request, RateCode $rate): RedirectResponse
    {
        $this->own($rate);

        return $this->saveDetail($request, $rate, null);
    }

    public function detailUpdate(Request $request, RateCode $rate, RateAmount $detail): RedirectResponse
    {
        $this->ownDetail($rate, $detail);

        return $this->saveDetail($request, $rate, $detail);
    }

    public function detailDestroy(RateCode $rate, RateAmount $detail): RedirectResponse
    {
        $this->ownDetail($rate, $detail);
        $detail->delete();

        return redirect()->route('setup.rates.edit', $rate)->with('status', __('pms.rates.detail_deleted'));
    }

    // ------------------------------------------------------------------

    private function save(Request $request, ?RateCode $rate): RedirectResponse
    {
        $p = app(Property::class);
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $this->dates($request, ['valid_from', 'valid_to'], $p);
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $p->id);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('pms_rate_codes', 'code')->where('property_id', $p->id)->whereNull('deleted_at')->ignore($rate?->id)],
            'name' => ['required', 'string', 'max:100'],
            'folio_text' => ['nullable', 'string', 'max:40'],
            'rate_category_id' => ['nullable', 'integer', $own('pms_rate_categories')],
            'new_category' => ['nullable', 'string', 'max:100'],
            'default_market_segment_id' => ['nullable', 'integer', $own('pms_market_segments')],
            'default_source_code_id' => ['nullable', 'integer', $own('pms_source_codes')],
            'complimentary_type' => ['nullable', Rule::in(['comp', 'house_use'])],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'min_los' => ['nullable', 'integer', 'min:0', 'max:999'], 'max_los' => ['nullable', 'integer', 'min:0', 'max:999'],
            'room_transaction_code_id' => ['required', 'integer', $own('pms_transaction_codes')],
            'weekend_transaction_code_id' => ['nullable', 'integer', $own('pms_transaction_codes')],
            'package_id' => ['nullable', 'integer', $own('pms_packages')],
            'currency_code' => ['required', Rule::exists('pms_currencies', 'code')],
            'min_advance_days' => ['nullable', 'integer', 'min:0', 'max:999'], 'max_advance_days' => ['nullable', 'integer', 'min:0', 'max:999'],
            'weekend' => ['array'], 'weekend.*' => ['integer', 'between:0,6'],
        ], [], [
            'code' => __('pms.rates.code'), 'name' => __('pms.common.description'), 'valid_from' => __('pms.rates.startsell'), 'valid_to' => __('pms.rates.endsell'),
            'room_transaction_code_id' => __('pms.rates.department_code'), 'currency_code' => __('pms.rates.currency'),
        ]);
        $data['weekend_days'] = array_sum(array_map(fn ($d) => 1 << (int) $d, array_unique($data['weekend'] ?? [])));
        unset($data['weekend']);
        foreach (['refresh_exchange_at_checkin', 'is_fixed_rate', 'is_flat_rate', 'print_rate', 'is_public', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $saved = $this->rates->save($p->id, $data, $rate);

        return redirect()->route('setup.rates.edit', $saved)->with('status', __('pms.common.saved', ['name' => $saved->code]));
    }

    private function saveDetail(Request $request, RateCode $rate, ?RateAmount $detail): RedirectResponse
    {
        $p = app(Property::class);
        $this->dates($request, ['date_from', 'date_to'], $p);
        foreach (RateCodeService::PRICE_FIELDS as $field) {
            $raw = trim((string) $request->input($field));
            $request->merge([$field => $raw === '' ? null : str_replace(',', '.', str_replace('.', '', $raw))]);
        }
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $p->id);
        $data = $request->validate([
            'season_id' => ['nullable', 'integer', $own('pms_seasons')],
            'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'market_segment_id' => ['nullable', 'integer', $own('pms_market_segments')],
            'source_code_id' => ['nullable', 'integer', $own('pms_source_codes')],
            'room_types' => ['array'], 'room_types.*' => ['integer', $own('pms_room_types')],
            'room_numbers' => ['nullable', 'string', 'max:250'],
            'days' => ['required', 'array', 'min:1'], 'days.*' => ['integer', 'between:0,6'],
        ] + array_fill_keys(RateCodeService::PRICE_FIELDS, ['nullable', 'numeric', 'min:0']), [
            'days.required' => __('pms.rates.err_days'),
        ], ['date_from' => __('pms.rates.start_date'), 'date_to' => __('pms.rates.end_date')]);

        if (collect(['price_default', 'price_1', 'price_2', 'price_3', 'price_4'])->every(fn ($f) => $data[$f] === null)) {
            return back()->withInput()->withErrors(['price_default' => __('pms.rates.err_no_price')]);
        }
        $rooms = ($data['room_numbers'] ?? null) ? implode(', ', \App\Domain\Rates\Services\RateCalculator::rooms($data['room_numbers'])) : null;
        if ($rooms) {
            $known = DB::table('pms_rooms')->where('property_id', $p->id)->whereIn('number', explode(', ', $rooms))->pluck('number')->all();
            $unknown = array_diff(explode(', ', $rooms), $known);
            if ($unknown) {
                return back()->withInput()->withErrors(['room_numbers' => __('pms.rates.err_rooms', ['rooms' => implode(', ', $unknown)])]);
            }
        }
        $roomTypes = array_map('intval', $data['room_types'] ?? []);
        $values = array_intersect_key($data, array_flip(array_merge(RateCodeService::PRICE_FIELDS, ['season_id', 'date_from', 'date_to', 'market_segment_id', 'source_code_id'])));
        $values['room_numbers'] = $rooms;
        $values['weekdays'] = array_sum(array_map(fn ($d) => 1 << (int) $d, array_unique($data['days'])));

        $saved = $this->rates->saveDetail($rate, $values, $roomTypes, $detail, $request->user()->id);

        return redirect()->route('setup.rates.edit', ['rate' => $rate, 'detay' => $saved->id])->with('status', __('pms.rates.detail_saved'));
    }

    private function form(RateCode $rate): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table, string $order = 'code') => DB::table($table)->where('property_id', $p)->orderBy($order)->get(['id', 'code', 'name']);

        return view('setup.rates.form', ['rate' => $rate] + ($rate->exists ? $this->details($rate) : ['details' => collect()]) + [
            'categories' => DB::table('pms_rate_categories')->where('property_id', $p)->orderBy('sort')->get(['id', 'code', 'name']),
            'segments' => $list('pms_market_segments', 'sort'), 'sources' => $list('pms_source_codes', 'sort'),
            'packages' => DB::table('pms_packages')->where('property_id', $p)->where('type', 'board')->whereNull('deleted_at')->orderBy('id')->get(['id', 'code', 'name']),
            'codes' => DB::table('pms_transaction_codes')->where('property_id', $p)->where('type', 'revenue')->where('is_active', true)
                ->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'currencies' => DB::table('pms_currencies')->where('is_active', true)->orderBy('sort')->get(['code', 'name']),
        ]);
    }

    /** @param array<int> $roomTypes */
    private function detailForm(RateCode $rate, RateAmount $detail, array $roomTypes, bool $copy = false): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table, string $order = 'code') => DB::table($table)->where('property_id', $p)->orderBy($order)->get(['id', 'code', 'name']);

        return view('setup.rates.detail', [
            'rate' => $rate, 'detail' => $detail, 'roomTypeIds' => $roomTypes, 'copy' => $copy,
            'roomTypes' => DB::table('pms_room_types')->where('property_id', $p)->orderBy('sort')->get(['id', 'code', 'name']),
            'seasons' => DB::table('pms_seasons')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->get(['id', 'code', 'name']),
            'segments' => $list('pms_market_segments', 'sort'), 'sources' => $list('pms_source_codes', 'sort'),
            'bands' => DB::table('pms_child_age_bands')->where('property_id', $p)->orderBy('band')->get(),
        ]);
    }

    /** @return array{details: \Illuminate\Support\Collection} */
    private function details(RateCode $rate): array
    {
        return ['details' => $rate->amounts()->with(['roomTypes:id,code', 'season:id,code,name'])->orderBy('date_from')->orderBy('id')->get()];
    }

    /** @param list<string> $fields */
    private function dates(Request $request, array $fields, Property $property): void
    {
        foreach ($fields as $field) {
            $raw = trim((string) $request->input($field));
            $parsed = $raw === '' ? null : BusinessDateInput::parse($raw, $property->current_business_date->toImmutable());
            $request->merge([$field => $parsed?->toDateString() ?? ($raw === '' ? null : 'x')]);
        }
    }

    private function own(RateCode $rate): void
    {
        abort_unless((int) $rate->property_id === (int) app(Property::class)->id, 404);
    }

    private function ownDetail(RateCode $rate, RateAmount $detail): void
    {
        $this->own($rate);
        abort_unless((int) $detail->rate_code_id === (int) $rate->id, 404);
    }
}
