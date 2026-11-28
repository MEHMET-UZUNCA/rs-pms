@extends('setup.catalog.layout', ['title' => __('pms.setup.payment_types'), 'subtitle' => __('pms.catalog.payment_sub')])
@section('catalog')
<table class="grid-table max-w-5xl" data-catalog="payment-types">
    <thead><tr><th class="w-20">{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="w-44">{{ __('pms.catalog.payment_kind') }}</th><th class="w-28">{{ __('pms.catalog.currency') }}</th>
        <th class="w-28 text-right">{{ __('pms.catalog.commission') }}</th><th class="w-20 text-center">{{ __('pms.catalog.drawer') }}</th><th class="w-16 text-center">{{ __('pms.rtypes.active') }}</th><th class="w-20"></th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        @php $f = 'pt-'.$r->id; @endphp
        <tr @class(['is-inactive' => ! $r->is_active])>
            <td class="font-semibold">{{ $r->code }}@if ($r->type === 'deposit') <span class="text-[11px] text-muted">{{ __('pms.catalog.deposit') }}</span>@endif</td><td>{{ $r->name }}</td>
            <td><select form="{{ $f }}" name="payment_kind" class="input" aria-label="{{ __('pms.catalog.payment_kind') }}">@foreach (\App\Http\Controllers\Setup\CatalogController::PAYMENT_KINDS as $k)<option value="{{ $k }}" @selected(($r->payment_kind ?? 'cash') === $k)>{{ __('pms.catalog.payment_kinds.'.$k) }}</option>@endforeach</select></td>
            <td><select form="{{ $f }}" name="currency_code" class="input" aria-label="{{ __('pms.catalog.currency') }}"><option value="">{{ __('pms.catalog.base_currency') }}</option>@foreach ($currencies as $c)<option value="{{ $c }}" @selected($r->currency_code === $c)>{{ $c }}</option>@endforeach</select></td>
            <td><input form="{{ $f }}" name="cc_commission_pct" value="{{ $r->cc_commission_pct !== null ? number_format((float) $r->cc_commission_pct, 2, ',', '') : '' }}" inputmode="decimal" class="input text-right" aria-label="{{ __('pms.catalog.commission') }}"></td>
            <td class="text-center"><input type="checkbox" form="{{ $f }}" name="open_cash_drawer" value="1" @checked($r->open_cash_drawer) aria-label="{{ __('pms.catalog.drawer') }}"></td>
            <td class="text-center"><input type="checkbox" form="{{ $f }}" name="is_active" value="1" @checked($r->is_active) aria-label="{{ __('pms.rtypes.active') }}"></td>
            <td><form method="POST" action="{{ route('setup.payment-types.update', $r->id) }}" id="{{ $f }}">@csrf @method('PUT')<button class="btn !h-7">{{ __('pms.common.ok') }}</button></form></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table>
<p class="text-[12.5px] text-muted mt-2 max-w-5xl">{{ __('pms.catalog.payment_hint') }} <a class="text-brand-600 hover:underline" href="{{ route('setup.transaction-codes.index') }}">{{ __('pms.menu.department_codes') }}</a></p>
@endsection
