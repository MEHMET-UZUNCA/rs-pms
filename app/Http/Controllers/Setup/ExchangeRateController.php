<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\ExchangeRateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Ayarlar → Döviz kurları (Fidelio Cashiering → Exchange Rates): günün otel kurları (Rate, Cash Rate, Ratecode Rate), banka kurları,
 * kaynak (TCMB / KKTC Merkez Bankası / elle), "Şimdi çek", döviz başına kâr payı kuralı ve elle kur.
 */
class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $rows = $this->rates->rules($p->id)->map(function ($rule) use ($p) {
            $rule->rate = $this->rates->current($p->id, $rule->currency_code);
            $rule->name = DB::table('pms_currencies')->where('code', $rule->currency_code)->value('name');

            return $rule;
        });

        return view('setup.exchange.index', [
            'rows' => $rows, 'source' => ExchangeRateService::source($p->id), 'businessDate' => $p->current_business_date,
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    public function fetch(Request $request): RedirectResponse
    {
        try {
            $count = $this->rates->fetch(app(Property::class)->id, null, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', __('pms.exchange.fetch_failed', ['error' => $e->getMessage()]));
        }

        return back()->with('status', __('pms.exchange.fetched', ['count' => $count]));
    }

    public function source(Request $request): RedirectResponse
    {
        $data = $request->validate(['source' => ['required', Rule::in(array_keys(ExchangeRateService::SOURCES))]]);
        ExchangeRateService::setSource(app(Property::class)->id, $data['source'], $request->user()->id);

        return back()->with('status', __('pms.exchange.source_saved'));
    }

    public function edit(string $currency): View
    {
        $p = app(Property::class);
        $rule = DB::table('pms_exchange_rate_rules')->where('property_id', $p->id)->where('currency_code', $currency)->first() ?? abort(404);

        return view('setup.exchange.form', [
            'rule' => $rule, 'rate' => $this->rates->current($p->id, $currency), 'businessDate' => $p->current_business_date,
            'name' => DB::table('pms_currencies')->where('code', $currency)->value('name'),
            'currencies' => DB::table('pms_currencies')->where('code', '!=', 'TRY')->orderBy('sort')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, string $currency): RedirectResponse
    {
        $p = app(Property::class);
        $ruleId = DB::table('pms_exchange_rate_rules')->where('property_id', $p->id)->where('currency_code', $currency)->value('id') ?? abort(404);
        foreach (['rate_margin_pct', 'cash_margin_pct', 'ratecode_margin_pct', 'forex_buy', 'forex_sell', 'cash_buy', 'cash_sell', 'posting_rate', 'cash_rate', 'ratecode_rate'] as $field) {
            $value = trim((string) $request->input($field));
            $request->merge([$field => $value === '' ? null : str_replace(',', '.', $value)]);
        }
        $rate = ['nullable', 'numeric', 'gt:0'];
        $data = $request->validate([
            'base' => ['required', Rule::in(ExchangeRateService::BASES)],
            'source_currency_code' => ['nullable', Rule::exists('pms_currencies', 'code')],
            'rate_margin_pct' => ['nullable', 'numeric', 'between:-50,50'], 'cash_margin_pct' => ['nullable', 'numeric', 'between:-50,50'],
            'ratecode_margin_pct' => ['nullable', 'numeric', 'between:-50,50'], 'is_auto' => ['boolean'],
            'forex_buy' => $rate, 'forex_sell' => $rate, 'cash_buy' => $rate, 'cash_sell' => $rate,
            'posting_rate' => $rate, 'cash_rate' => $rate, 'ratecode_rate' => $rate,
        ]);

        DB::table('pms_exchange_rate_rules')->where('id', $ruleId)->update([
            'base' => $data['base'], 'source_currency_code' => ($data['source_currency_code'] ?? null) ?: null,
            'rate_margin_pct' => $data['rate_margin_pct'] ?? 0, 'cash_margin_pct' => $data['cash_margin_pct'] ?? 0, 'ratecode_margin_pct' => $data['ratecode_margin_pct'] ?? 0,
            'is_auto' => $request->boolean('is_auto'), 'updated_by' => $request->user()->id, 'updated_at' => now(),
        ]);

        $manual = array_filter(array_intersect_key($data, array_flip(['forex_buy', 'forex_sell', 'cash_buy', 'cash_sell', 'posting_rate', 'cash_rate', 'ratecode_rate'])), fn ($v) => $v !== null);
        if ($manual !== []) {
            $this->rates->manual($p->id, $currency, array_map('floatval', $manual), $request->user()->id);
        } elseif ($rate = $this->rates->current($p->id, $currency, $p->current_business_date->toDateString())) {
            // Kural değişti: bugünün kurunu bankanın son kurlarıyla yeniden hesapla
            if ($rate->business_date->toDateString() === $p->current_business_date->toDateString() && ($rate->buy_rate || $rate->sell_rate)) {
                $this->rates->manual($p->id, $currency, array_filter(['forex_buy' => (float) $rate->buy_rate ?: null, 'forex_sell' => (float) $rate->sell_rate ?: null,
                    'cash_buy' => (float) $rate->cash_buy_rate ?: null, 'cash_sell' => (float) $rate->cash_sell_rate ?: null]), $request->user()->id, $rate->source);
            }
        }

        return redirect()->route('setup.exchange.index')->with('status', __('pms.common.saved', ['name' => $currency]))->with('selected', $ruleId);
    }
}
