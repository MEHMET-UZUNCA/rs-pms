@extends('setup.catalog.layout', ['title' => __('pms.menu.taxes'), 'subtitle' => __('pms.catalog.taxes_sub')])
@section('catalog')
@php $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : ''; @endphp
<table class="grid-table min-w-[80rem]" data-catalog="taxes">
    <thead><tr><th class="w-24">{{ __('pms.common.code') }}</th><th class="min-w-48">{{ __('pms.common.name') }}</th><th class="w-28">{{ __('pms.catalog.calc') }}</th><th class="w-20 text-right">{{ __('pms.catalog.rate') }}</th>
        <th class="w-24 text-right">{{ __('pms.catalog.fixed_amount') }}</th><th class="w-16 text-center" title="{{ __('pms.catalog.inclusive_hint') }}">{{ __('pms.catalog.inclusive') }}</th>
        <th class="w-28">{{ __('pms.ooo.from') }}</th><th class="w-28">{{ __('pms.ooo.to') }}</th><th class="w-24">Logo</th><th class="w-16 text-center">{{ __('pms.catalog.print_separately') }}</th>
        <th class="w-16 text-right">{{ __('pms.catalog.codes') }}</th><th class="w-36"></th></tr></thead>
    <tbody>
    @foreach ($rows->push(null) as $r)
        @php $f = $r ? 't-'.$r->id : 't-new'; $locked = $r && ($posted[$r->id] ?? 0) > 0; @endphp
        <tr @class(['bg-brand-50/40' => ! $r, 'is-inactive' => $r && $r->valid_to && $r->valid_to < now()->toDateString()])>
            <td><input form="{{ $f }}" name="code" value="{{ $r?->code ?? old('code') }}" maxlength="10" class="input uppercase" aria-label="{{ __('pms.common.code') }}"></td>
            <td><input form="{{ $f }}" name="name" value="{{ $r?->name ?? old('name') }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
            <td><select form="{{ $f }}" name="calc_type" class="input" @if ($locked) title="{{ __('pms.catalog.locked_hint') }}" @endif aria-label="{{ __('pms.catalog.calc') }}">@foreach (['percent', 'amount'] as $c)<option value="{{ $c }}" @selected(($r?->calc_type ?? 'percent') === $c)>{{ __('pms.catalog.calc_types.'.$c) }}</option>@endforeach</select></td>
            <td><input form="{{ $f }}" name="rate" value="{{ $r ? rtrim(rtrim(number_format((float) $r->rate, 3, ',', ''), '0'), ',') : '' }}" inputmode="decimal" class="input text-right" @if ($locked) readonly title="{{ __('pms.catalog.locked_hint') }}" @endif aria-label="{{ __('pms.catalog.rate') }}"></td>
            <td><input form="{{ $f }}" name="amount" value="{{ $r?->amount !== null && $r ? number_format((float) $r->amount, 2, ',', '') : '' }}" inputmode="decimal" class="input text-right" @if ($locked) readonly @endif aria-label="{{ __('pms.catalog.fixed_amount') }}"></td>
            <td class="text-center">@if ($locked)<input type="hidden" form="{{ $f }}" name="is_inclusive" value="{{ $r->is_inclusive ? 1 : 0 }}">@endif<input type="checkbox" form="{{ $f }}" name="is_inclusive" value="1" @checked($r ? $r->is_inclusive : true) @disabled($locked) aria-label="{{ __('pms.catalog.inclusive') }}"></td>
            <td><input form="{{ $f }}" name="valid_from" value="{{ $r ? $d($r->valid_from) : now()->format('d.m.Y') }}" class="input" autocomplete="off" aria-label="{{ __('pms.ooo.from') }}"></td>
            <td><input form="{{ $f }}" name="valid_to" value="{{ $r ? $d($r->valid_to) : '' }}" class="input" autocomplete="off" aria-label="{{ __('pms.ooo.to') }}"></td>
            <td><input form="{{ $f }}" name="logo_tax_code" value="{{ $r?->logo_tax_code }}" maxlength="20" class="input" aria-label="Logo"></td>
            <td class="text-center"><input type="checkbox" form="{{ $f }}" name="print_separately" value="1" @checked($r?->print_separately) aria-label="{{ __('pms.catalog.print_separately') }}"></td>
            <td class="text-right">{{ $r ? ($codes[$r->id] ?? 0) : '' }}</td>
            <td class="whitespace-nowrap">@include('setup.catalog._row-actions', ['row' => $r, 'form' => $f, 'save' => $r ? route('setup.taxes.update', $r->id) : route('setup.taxes.store'), 'delete' => $r && ! $locked ? route('setup.taxes.destroy', $r->id) : null])</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="text-[12.5px] text-muted mt-2 max-w-5xl">{{ __('pms.catalog.taxes_hint') }}</p>
@endsection
