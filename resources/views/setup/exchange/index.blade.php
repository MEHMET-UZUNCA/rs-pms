@extends('layouts.app')
@section('title', __('pms.exchange.title'))
@section('content')
@php
    $r4 = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.exchange.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.exchange.subtitle', ['date' => $businessDate->format('d.m.Y')]) }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>
        <section class="flex-1 min-w-0 flex flex-col">
            <form method="POST" action="{{ route('setup.exchange.source') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                @csrf @method('PUT')
                <label class="flex items-center gap-2">{{ __('pms.exchange.source') }}
                    <select name="source" class="input w-64" onchange="this.form.submit()">
                        @foreach (array_keys(\App\Domain\Core\Services\ExchangeRateService::SOURCES) as $key)
                            <option value="{{ $key }}" @selected($source === $key)>{{ __('pms.exchange.sources.'.$key) }}</option>
                        @endforeach
                    </select>
                </label>
                <span class="text-[12px] text-muted">{{ __('pms.exchange.schedule_hint') }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="fx-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.description') }}</th><th>{{ __('pms.common.date') }}</th>
                        <th class="text-right">{{ __('pms.exchange.rate') }}</th><th class="text-right">{{ __('pms.exchange.cash_rate') }}</th><th class="text-right">{{ __('pms.exchange.ratecode_rate') }}</th>
                        <th>{{ __('pms.exchange.base') }}</th><th class="text-right">{{ __('pms.exchange.margins') }}</th>
                        <th class="text-right">{{ __('pms.exchange.bank_forex') }}</th><th class="text-right">{{ __('pms.exchange.bank_cash') }}</th><th>{{ __('pms.exchange.source') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $row)
                        @php $rate = $row->rate; $stale = $rate && $rate->business_date->lt($businessDate); @endphp
                        <tr data-id="{{ $row->id }}" data-url-edit="{{ route('setup.exchange.edit', $row->currency_code) }}" @class(['is-inactive' => ! $row->is_auto])>
                            <td class="font-semibold">{{ $row->currency_code }}</td>
                            <td>{{ $row->name }}@if ($row->source_currency_code) <span class="text-muted">→ {{ $row->source_currency_code }}</span>@endif</td>
                            <td @class(['text-danger' => $stale])>{{ $rate?->business_date->format('d.m.Y') ?? '—' }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ $r4($rate?->posting_rate) }}</td>
                            <td class="text-right tabular-nums">{{ $r4($rate?->cash_rate) }}</td>
                            <td class="text-right tabular-nums">{{ $r4($rate?->ratecode_rate) }}</td>
                            <td>{{ __('pms.exchange.bases.'.$row->base) }}</td>
                            <td class="text-right tabular-nums text-[12px]">{{ (float) $row->rate_margin_pct }} / {{ (float) $row->cash_margin_pct }} / {{ (float) $row->ratecode_margin_pct }} %</td>
                            <td class="text-right tabular-nums text-[12px] text-muted">{{ $r4($rate?->buy_rate) }} / {{ $r4($rate?->sell_rate) }}</td>
                            <td class="text-right tabular-nums text-[12px] text-muted">{{ $r4($rate?->cash_buy_rate) }} / {{ $r4($rate?->cash_sell_rate) }}</td>
                            <td class="text-[12px]">{{ $rate ? __('pms.exchange.sources.'.$rate->source) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <form method="POST" action="{{ route('setup.exchange.fetch') }}">@csrf
                <button class="btn btn-side w-full" data-hotkey="alt+f" @disabled($source === 'manual')>{{ __('pms.exchange.fetch') }} <span class="kbd ml-auto">Alt+F</span></button>
            </form>
            <a class="btn btn-side" data-needs-selection="fx-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
