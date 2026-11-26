@extends('layouts.app')
@section('title', __('pms.exchange.edit_title', ['code' => $rule->currency_code]))
@section('content')
@php
    $v = fn (string $field, $default = '') => old($field, $default);
    $today = $rate && $rate->business_date->isSameDay($businessDate);
    $n = fn ($x) => $x === null ? '' : rtrim(rtrim(number_format((float) $x, 6, ',', ''), '0'), ',');
@endphp
<form method="POST" action="{{ route('setup.exchange.update', $rule->currency_code) }}" class="win flex-1">
    @csrf @method('PUT')
    <div class="win-title"><span>{{ __('pms.exchange.edit_title', ['code' => $rule->currency_code]) }} — {{ $name }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-2">
        <section class="win !min-h-fit p-3 grid grid-cols-[minmax(0,1fr)_10rem] items-center gap-x-3 gap-y-2">
            <b class="col-span-2">{{ __('pms.exchange.rule') }}</b>
            <label class="field-label !mb-0" for="base">{{ __('pms.exchange.base') }}</label>
            <select id="base" name="base" class="input">
                @foreach (\App\Domain\Core\Services\ExchangeRateService::BASES as $base)
                    <option value="{{ $base }}" @selected($v('base', $rule->base) === $base)>{{ __('pms.exchange.bases.'.$base) }}</option>
                @endforeach
            </select>
            <label class="field-label !mb-0" for="source_currency_code">{{ __('pms.exchange.source_currency') }}</label>
            <select id="source_currency_code" name="source_currency_code" class="input">
                <option value="">{{ __('pms.common.same') }}</option>
                @foreach ($currencies as $c)<option value="{{ $c->code }}" @selected($v('source_currency_code', $rule->source_currency_code) === $c->code)>{{ $c->code }}</option>@endforeach
            </select>
            @foreach (['rate_margin_pct', 'cash_margin_pct', 'ratecode_margin_pct'] as $field)
                <label class="field-label !mb-0" for="{{ $field }}">{{ __('pms.exchange.'.$field) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ $v($field, (float) $rule->{$field}) }}" class="input text-right" inputmode="decimal">
            @endforeach
            <span></span>
            <label class="flex items-center gap-2"><input type="hidden" name="is_auto" value="0"><input type="checkbox" name="is_auto" value="1" @checked($v('is_auto', $rule->is_auto))> {{ __('pms.exchange.auto') }}</label>
            <p class="col-span-2 text-[12px] text-muted">{{ __('pms.exchange.rule_hint') }}</p>
        </section>
        <section class="win !min-h-fit p-3 grid grid-cols-[minmax(0,1fr)_10rem] items-center gap-x-3 gap-y-2">
            <b class="col-span-2">{{ __('pms.exchange.manual', ['date' => $businessDate->format('d.m.Y')]) }}</b>
            @foreach (['forex_buy' => 'buy_rate', 'forex_sell' => 'sell_rate', 'cash_buy' => 'cash_buy_rate', 'cash_sell' => 'cash_sell_rate'] as $field => $column)
                <label class="field-label !mb-0" for="{{ $field }}">{{ __('pms.exchange.bases.'.$field) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ $v($field) }}" placeholder="{{ $n($rate?->{$column}) }}" class="input text-right" inputmode="decimal">
            @endforeach
            @foreach (['posting_rate' => 'rate', 'cash_rate' => 'cash_rate', 'ratecode_rate' => 'ratecode_rate'] as $field => $label)
                <label class="field-label !mb-0" for="{{ $field }}">{{ __('pms.exchange.'.$label) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ $v($field) }}" placeholder="{{ $n($rate?->{$field}) }}" class="input text-right" inputmode="decimal">
            @endforeach
            <p class="col-span-2 text-[12px] text-muted">{{ __('pms.exchange.manual_hint') }}</p>
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.exchange.index', ['secili' => $rule->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
