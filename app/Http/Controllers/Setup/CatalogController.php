<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\Audit\Audit;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar ağacının küçük tanım ekranları (Fidelio Configuration):
 *  - Rate Categories (fiyat kodu grupları), Rate Seasons (sezonlar ve tarih aralıkları),
 *  - Department Code Groups (ana grup / grup), Taxes (vergiler), Payment Types (ödeme departman kodlarının ödeme türü).
 * Kullanılan kayıt silinmez. Kayıtlarda kullanılmış verginin oranı / türü değiştirilmez: bitiş tarihi verilir, yeni vergi açılır.
 */
class CatalogController extends Controller
{
    public const GROUP_TYPES = ['revenue', 'non_revenue', 'payment'];

    public const PAYMENT_KINDS = ['cash', 'foreign_cash', 'card', 'bank_transfer', 'city_ledger', 'cheque', 'voucher'];

    private function p(): int
    {
        return app(Property::class)->id;
    }

    private function code(Request $request, int $max = 10): void
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
    }

    // ------------------------------------------------------------ Rate Categories

    public function categories(): View
    {
        $p = $this->p();

        return view('setup.catalog.categories', [
            'rows' => DB::table('pms_rate_categories')->where('property_id', $p)->orderBy('sort')->orderBy('code')->get(),
            'used' => DB::table('pms_rate_codes')->where('property_id', $p)->whereNull('deleted_at')->groupBy('rate_category_id')->selectRaw('rate_category_id, COUNT(*) AS n')->pluck('n', 'rate_category_id'),
        ]);
    }

    public function saveCategory(Request $request, ?int $id = null): RedirectResponse
    {
        $p = $this->p();
        $id && abort_unless(DB::table('pms_rate_categories')->where('id', $id)->where('property_id', $p)->exists(), 404);
        $this->code($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('pms_rate_categories', 'code')->where('property_id', $p)->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'sort' => ['nullable', 'integer', 'between:0,9999'],
        ]);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $id ? DB::table('pms_rate_categories')->where('id', $id)->update($data + ['updated_at' => now()])
            : DB::table('pms_rate_categories')->insert($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function deleteCategory(int $id): RedirectResponse
    {
        $row = DB::table('pms_rate_categories')->where('id', $id)->where('property_id', $this->p())->first();
        abort_unless($row, 404);
        if (DB::table('pms_rate_codes')->where('rate_category_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.in_use', ['code' => $row->code]));
        }
        DB::table('pms_rate_categories')->where('id', $id)->delete();

        return back()->with('status', __('pms.catalog.deleted', ['code' => $row->code]));
    }

    // ------------------------------------------------------------ Rate Seasons

    public function seasons(): View
    {
        $p = $this->p();
        $seasons = DB::table('pms_seasons')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->orderBy('code')->get();

        return view('setup.catalog.seasons', [
            'rows' => $seasons,
            'dates' => DB::table('pms_season_dates')->whereIn('season_id', $seasons->pluck('id'))->orderBy('date_from')->get()->groupBy('season_id'),
            'used' => DB::table('pms_rate_amounts')->whereNotNull('season_id')->groupBy('season_id')->selectRaw('season_id, COUNT(*) AS n')->pluck('n', 'season_id'),
        ]);
    }

    public function saveSeason(Request $request, ?int $id = null): RedirectResponse
    {
        $p = $this->p();
        $id && abort_unless(DB::table('pms_seasons')->where('id', $id)->where('property_id', $p)->exists(), 404);
        $this->code($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('pms_seasons', 'code')->where('property_id', $p)->whereNull('deleted_at')->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'sort' => ['nullable', 'integer', 'between:0,9999'],
        ]);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $id ? DB::table('pms_seasons')->where('id', $id)->update($data + ['updated_at' => now()])
            : DB::table('pms_seasons')->insert($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function deleteSeason(int $id): RedirectResponse
    {
        $row = DB::table('pms_seasons')->where('id', $id)->where('property_id', $this->p())->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        if (DB::table('pms_rate_amounts')->where('season_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.in_use', ['code' => $row->code]));
        }
        DB::table('pms_season_dates')->where('season_id', $id)->delete();
        DB::table('pms_seasons')->where('id', $id)->delete();

        return back()->with('status', __('pms.catalog.deleted', ['code' => $row->code]));
    }

    /** Sezona tarih aralığı: başka bir sezonun aralığıyla çakışamaz. */
    public function addSeasonDates(Request $request, int $id): RedirectResponse
    {
        $p = $this->p();
        abort_unless(DB::table('pms_seasons')->where('id', $id)->where('property_id', $p)->exists(), 404);
        $bd = app(Property::class)->current_business_date->toImmutable();
        foreach (['date_from', 'date_to'] as $f) {
            $request->merge([$f => BusinessDateInput::parse((string) $request->input($f), $bd)?->toDateString() ?? $request->input($f)]);
        }
        $data = $request->validate(['date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from']], [],
            ['date_from' => __('pms.ooo.from'), 'date_to' => __('pms.ooo.to')]);
        $clash = DB::table('pms_season_dates as d')->join('pms_seasons as s', 's.id', '=', 'd.season_id')->where('s.property_id', $p)->whereNull('s.deleted_at')
            ->where('d.date_from', '<=', $data['date_to'])->where('d.date_to', '>=', $data['date_from'])->first(['s.code', 'd.date_from', 'd.date_to']);
        if ($clash) {
            return back()->withInput()->with('error', __('pms.catalog.season_overlap', ['code' => $clash->code,
                'from' => CarbonImmutable::parse($clash->date_from)->format('d.m.Y'), 'to' => CarbonImmutable::parse($clash->date_to)->format('d.m.Y')]));
        }
        DB::table('pms_season_dates')->insert(['season_id' => $id] + $data);

        return back()->with('status', __('pms.catalog.dates_added'));
    }

    public function deleteSeasonDates(int $id, int $range): RedirectResponse
    {
        abort_unless(DB::table('pms_seasons')->where('id', $id)->where('property_id', $this->p())->exists(), 404);
        DB::table('pms_season_dates')->where('id', $range)->where('season_id', $id)->delete();

        return back()->with('status', __('pms.catalog.dates_deleted'));
    }

    // ------------------------------------------------------------ Department Code Groups

    public function groups(): View
    {
        $p = $this->p();
        $rows = DB::table('pms_transaction_code_groups')->where('property_id', $p)->orderBy('sort')->orderBy('code')->get();

        return view('setup.catalog.groups', [
            'rows' => $rows,
            'used' => DB::table('pms_transaction_codes')->where('property_id', $p)->whereNull('deleted_at')->groupBy('group_id')->selectRaw('group_id, COUNT(*) AS n')->pluck('n', 'group_id'),
            'parents' => $rows->whereNull('parent_id')->values(),
        ]);
    }

    public function saveGroup(Request $request, ?int $id = null): RedirectResponse
    {
        $p = $this->p();
        $id && abort_unless(DB::table('pms_transaction_code_groups')->where('id', $id)->where('property_id', $p)->exists(), 404);
        $this->code($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('pms_transaction_code_groups', 'code')->where('property_id', $p)->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(self::GROUP_TYPES)],
            'parent_id' => ['nullable', 'integer', Rule::exists('pms_transaction_code_groups', 'id')->where('property_id', $p)->whereNull('parent_id'), Rule::notIn([$id ?? 0])],
            'sort' => ['nullable', 'integer', 'between:0,9999'],
        ], [], ['parent_id' => __('pms.catalog.main_group')]);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        if ($id && $data['parent_id'] && DB::table('pms_transaction_code_groups')->where('parent_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.group_has_children'));
        }
        $id ? DB::table('pms_transaction_code_groups')->where('id', $id)->update($data + ['updated_at' => now()])
            : DB::table('pms_transaction_code_groups')->insert($data + ['property_id' => $p, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function deleteGroup(int $id): RedirectResponse
    {
        $row = DB::table('pms_transaction_code_groups')->where('id', $id)->where('property_id', $this->p())->first();
        abort_unless($row, 404);
        if (DB::table('pms_transaction_codes')->where('group_id', $id)->exists() || DB::table('pms_transaction_code_groups')->where('parent_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.in_use', ['code' => $row->code]));
        }
        DB::table('pms_transaction_code_groups')->where('id', $id)->delete();

        return back()->with('status', __('pms.catalog.deleted', ['code' => $row->code]));
    }

    // ------------------------------------------------------------ Taxes

    public function taxes(): View
    {
        $p = $this->p();
        $rows = DB::table('pms_taxes')->where('property_id', $p)->whereNull('deleted_at')->orderBy('sort')->orderBy('rate')->orderBy('code')->get();

        return view('setup.catalog.taxes', [
            'rows' => $rows,
            'codes' => DB::table('pms_transaction_code_taxes')->whereIn('tax_id', $rows->pluck('id'))->groupBy('tax_id')->selectRaw('tax_id, COUNT(DISTINCT transaction_code_id) AS n')->pluck('n', 'tax_id'),
            'posted' => DB::table('pms_posting_taxes')->whereIn('tax_id', $rows->pluck('id'))->groupBy('tax_id')->selectRaw('tax_id, COUNT(*) AS n')->pluck('n', 'tax_id'),
        ]);
    }

    public function saveTax(Request $request, ?int $id = null): RedirectResponse
    {
        $p = $this->p();
        $old = $id ? DB::table('pms_taxes')->where('id', $id)->where('property_id', $p)->whereNull('deleted_at')->first() : null;
        $id && abort_unless($old, 404);
        $this->code($request);
        $bd = app(Property::class)->current_business_date->toImmutable();
        foreach (['valid_from', 'valid_to'] as $f) {
            $raw = trim((string) $request->input($f));
            $request->merge([$f => $raw === '' ? null : (BusinessDateInput::parse($raw, $bd)?->toDateString() ?? $raw)]);
        }
        $request->merge(['rate' => str_replace(',', '.', (string) $request->input('rate')) ?: 0, 'amount' => str_replace(',', '.', (string) $request->input('amount')) ?: null]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('pms_taxes', 'code')->where('property_id', $p)->whereNull('deleted_at')->ignore($id)],
            'name' => ['required', 'string', 'max:100'], 'calc_type' => ['required', Rule::in(['percent', 'amount'])],
            'rate' => ['required', 'numeric', 'between:0,100'], 'amount' => ['nullable', 'numeric', 'between:0,999999'],
            'valid_from' => ['required', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'logo_tax_code' => ['nullable', 'string', 'max:20'], 'sort' => ['nullable', 'integer', 'between:0,9999'],
        ], [], ['rate' => __('pms.catalog.rate'), 'valid_from' => __('pms.ooo.from'), 'valid_to' => __('pms.ooo.to')]);
        $data += ['is_inclusive' => $request->boolean('is_inclusive'), 'print_separately' => $request->boolean('print_separately')];
        $data['sort'] = (int) ($data['sort'] ?? 0);
        if ($old && DB::table('pms_posting_taxes')->where('tax_id', $id)->exists()) {
            $changed = (float) $old->rate !== (float) $data['rate'] || $old->calc_type !== $data['calc_type'] || (bool) $old->is_inclusive !== $data['is_inclusive']
                || (float) ($old->amount ?? 0) !== (float) ($data['amount'] ?? 0);
            if ($changed) {
                return back()->with('error', __('pms.catalog.tax_used', ['code' => $old->code]));
            }
        }
        if ($id) {
            DB::table('pms_taxes')->where('id', $id)->update($data + ['updated_at' => now()]);
            Audit::record('tax', $id, 'updated', array_intersect_key((array) $old, $data), $data, null, $p);
        } else {
            $id = DB::table('pms_taxes')->insertGetId($data + ['property_id' => $p, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('tax', $id, 'created', null, $data, null, $p);
        }

        return back()->with('status', __('pms.common.saved', ['name' => $data['code']]));
    }

    public function deleteTax(int $id): RedirectResponse
    {
        $row = DB::table('pms_taxes')->where('id', $id)->where('property_id', $this->p())->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        if (DB::table('pms_posting_taxes')->where('tax_id', $id)->exists() || DB::table('pms_transaction_code_taxes')->where('tax_id', $id)->exists()) {
            return back()->with('error', __('pms.catalog.in_use', ['code' => $row->code]));
        }
        DB::table('pms_taxes')->where('id', $id)->delete();
        Audit::record('tax', $id, 'deleted', (array) $row, null, null, $this->p());

        return back()->with('status', __('pms.catalog.deleted', ['code' => $row->code]));
    }

    // ------------------------------------------------------------ Payment Types

    public function paymentTypes(): View
    {
        $p = $this->p();

        return view('setup.catalog.payment-types', [
            'rows' => DB::table('pms_transaction_codes')->where('property_id', $p)->whereNull('deleted_at')->whereIn('type', ['payment', 'deposit'])
                ->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name', 'type', 'payment_kind', 'currency_code', 'cc_commission_pct', 'open_cash_drawer', 'is_active']),
            'currencies' => DB::table('pms_currencies')->where('is_active', true)->orderBy('code')->pluck('code'),
        ]);
    }

    public function savePaymentType(Request $request, int $id): RedirectResponse
    {
        $p = $this->p();
        $row = DB::table('pms_transaction_codes')->where('id', $id)->where('property_id', $p)->whereIn('type', ['payment', 'deposit'])->first();
        abort_unless($row, 404);
        $request->merge(['cc_commission_pct' => str_replace(',', '.', (string) $request->input('cc_commission_pct')) ?: null]);
        $data = $request->validate([
            'payment_kind' => ['required', Rule::in(self::PAYMENT_KINDS)],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('pms_currencies', 'code')],
            'cc_commission_pct' => ['nullable', 'numeric', 'between:0,20'],
        ]);
        $data += ['open_cash_drawer' => $request->boolean('open_cash_drawer'), 'is_active' => $request->boolean('is_active'), 'updated_at' => now()];
        DB::table('pms_transaction_codes')->where('id', $id)->update($data);

        return back()->with('status', __('pms.common.saved', ['name' => $row->code]));
    }
}
