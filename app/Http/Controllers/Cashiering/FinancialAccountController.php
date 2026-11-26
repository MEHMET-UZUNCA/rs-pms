<?php

namespace App\Http\Controllers\Cashiering;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Core\Models\Property;
use App\Domain\Profiles\Models\Profile;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ön Büro → New Financial Account (PM hesabı): rezervasyonsuz hesap açma ve düzenleme.
 * Alanlar Fidelio penceresindeki gibi; otelin kullanmadığı kanal kodu ve kredi limiti tipi alınmadı.
 */
class FinancialAccountController extends Controller
{
    public function __construct(private readonly FolioService $folios) {}

    public function create(): View
    {
        $property = app(Property::class);
        $pm = DB::table('pms_folio_groups')->where('property_id', $property->id)->where('code', 'PM')->value('id');

        return $this->form(new Folio(['folio_group_id' => $pm, 'currency_code' => $property->base_currency]));
    }

    public function edit(Folio $folio): View
    {
        $this->own($folio);

        return $this->form($folio);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, Folio $folio): RedirectResponse
    {
        $this->own($folio);

        return $this->save($request, $folio);
    }

    private function save(Request $request, ?Folio $folio): RedirectResponse
    {
        $property = app(Property::class);
        $bd = $property->current_business_date->toImmutable();
        foreach (['valid_from', 'valid_until'] as $field) {
            $raw = trim((string) $request->input($field));
            $parsed = $raw === '' ? null : BusinessDateInput::parse($raw, $bd);
            $request->merge([$field => $parsed?->toDateString() ?? ($raw === '' ? null : 'x')]);
        }
        $request->merge(['credit_limit' => str_replace(['.', ','], ['', '.'], (string) $request->input('credit_limit')) ?: null]);
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $property->id);

        $data = $request->validate([
            'folio_group_id' => ['nullable', 'integer', $own('pms_folio_groups')],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'payee_profile_id' => ['nullable', 'integer', $own('pms_profiles')],
            'folio_number' => ['nullable', 'string', 'max:20', Rule::unique('pms_folios', 'folio_number')->where('property_id', $property->id)->ignore($folio?->id)],
            'name' => ['required', 'string', 'max:100'],
            'market_segment_id' => ['nullable', 'integer', $own('pms_market_segments')],
            'source_code_id' => ['nullable', 'integer', $own('pms_source_codes')],
            'currency_code' => ['nullable', Rule::exists('pms_currencies', 'code')],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_info' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'no_post' => ['boolean'],
        ], [], [
            'folio_group_id' => __('pms.fa.group'), 'valid_from' => __('pms.fa.valid_from'), 'valid_until' => __('pms.fa.valid_until'),
            'payee_profile_id' => __('pms.fa.profile'), 'folio_number' => __('pms.fa.number'), 'name' => __('pms.common.description'),
            'market_segment_id' => __('pms.fa.market'), 'source_code_id' => __('pms.fa.source'), 'currency_code' => __('pms.fa.currency'),
            'credit_limit' => __('pms.fa.credit_limit'), 'billing_info' => __('pms.fa.billing_info'), 'notes' => __('pms.fa.remarks'),
        ]);
        $data['no_post'] = $request->boolean('no_post');

        $saved = $this->folios->saveAccount($property->id, $data, $folio, $request->user()->id);

        return redirect()->route('billing.account', $saved)->with('status', __('pms.common.saved', ['name' => $saved->folio_number.' '.$saved->name]));
    }

    private function form(Folio $folio): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table) => DB::table($table)->where('property_id', $p)->orderBy('code')->get(['id', 'code', 'name']);

        return view('cashiering.financial-account', [
            'folio' => $folio,
            'groups' => $list('pms_folio_groups'), 'segments' => $list('pms_market_segments'), 'sources' => $list('pms_source_codes'),
            'currencies' => DB::table('pms_currencies')->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'profiles' => Profile::where('property_id', $p)->whereIn('type', ['company', 'travel_agent', 'agent', 'source', 'group'])
                ->orderBy('display_name')->get(['id', 'display_name', 'type']),
        ]);
    }

    private function own(Folio $folio): void
    {
        abort_unless((int) $folio->property_id === (int) app(Property::class)->id && ! $folio->reservation_id, 404);
    }
}
