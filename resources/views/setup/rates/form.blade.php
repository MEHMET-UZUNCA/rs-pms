@extends('layouts.app')
@section('title', $rate->exists ? __('pms.rates.edit_title', ['code' => $rate->code]) : __('pms.rates.new_title'))
@section('content')
@php
    $v = fn (string $f) => old($f, $rate->{$f});
    $sel = fn (string $f, $id) => (string) old($f, $rate->{$f}) === (string) $id;
    $date = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y') : '';
    $money = fn ($x) => $x === null ? '' : number_format((float) $x, 2, ',', '.');
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $weekend = old('weekend', collect(range(0, 6))->filter(fn ($i) => ((int) $rate->weekend_days) & (1 << $i))->all());
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ $rate->exists ? __('pms.rates.edit_title', ['code' => $rate->code]) : __('pms.rates.new_title') }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ $rate->exists ? route('setup.rates.update', $rate) : route('setup.rates.store') }}" id="rate-form" class="p-3 grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] border-b border-line shrink-0">
        @csrf
        @if ($rate->exists) @method('PUT') @endif
        <div class="grid grid-cols-2 gap-x-3 gap-y-2 content-start">
            <div class="grid grid-cols-[10rem_1fr] gap-2">
                <div><label class="field-label" for="code">{{ __('pms.rates.code') }} *</label>
                    <input id="code" name="code" value="{{ $v('code') }}" maxlength="20" class="input uppercase @error('code') input-error @enderror" required autofocus></div>
                <div><label class="field-label" for="name">{{ __('pms.common.description') }} *</label>
                    <input id="name" name="name" value="{{ $v('name') }}" maxlength="100" class="input @error('name') input-error @enderror" required></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="field-label" for="rate_category_id">{{ __('pms.rates.category') }}</label>
                    <select id="rate_category_id" name="rate_category_id" class="input"><option value=""></option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($sel('rate_category_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="field-label" for="new_category">{{ __('pms.rates.new_category') }}</label>
                    <input id="new_category" name="new_category" value="{{ old('new_category') }}" maxlength="100" class="input" placeholder="2028 FIYATLARI"></div>
            </div>

            <div><label class="field-label" for="folio_text">{{ __('pms.rates.folio_text') }}</label>
                <input id="folio_text" name="folio_text" value="{{ $v('folio_text') }}" maxlength="40" class="input"></div>
            <div><label class="field-label" for="default_market_segment_id">{{ __('pms.rates.market') }}</label>
                <select id="default_market_segment_id" name="default_market_segment_id" class="input"><option value=""></option>
                    @foreach ($segments as $s)<option value="{{ $s->id }}" @selected($sel('default_market_segment_id', $s->id))>{{ $s->code }}  {{ $s->name }}</option>@endforeach</select></div>

            <div class="grid grid-cols-2 gap-2">
                <div><label class="field-label" for="valid_from">{{ __('pms.rates.startsell') }}</label>
                    <input id="valid_from" name="valid_from" value="{{ old('valid_from', $date($rate->valid_from)) }}" class="input @error('valid_from') input-error @enderror" autocomplete="off"></div>
                <div><label class="field-label" for="valid_to">{{ __('pms.rates.endsell') }}</label>
                    <input id="valid_to" name="valid_to" value="{{ old('valid_to', $date($rate->valid_to)) }}" class="input @error('valid_to') input-error @enderror" autocomplete="off"></div>
            </div>
            <div><label class="field-label" for="default_source_code_id">{{ __('pms.rates.source') }}</label>
                <select id="default_source_code_id" name="default_source_code_id" class="input"><option value=""></option>
                    @foreach ($sources as $s)<option value="{{ $s->id }}" @selected($sel('default_source_code_id', $s->id))>{{ $s->code }}  {{ $s->name }}</option>@endforeach</select></div>

            <div class="grid grid-cols-2 gap-2">
                <div><label class="field-label" for="min_los">{{ __('pms.rates.min_los') }}</label><input id="min_los" name="min_los" value="{{ $v('min_los') }}" class="input" inputmode="numeric"></div>
                <div><label class="field-label" for="max_los">{{ __('pms.rates.max_los') }}</label><input id="max_los" name="max_los" value="{{ $v('max_los') }}" class="input" inputmode="numeric"></div>
            </div>
            <div><label class="field-label" for="complimentary_type">{{ __('pms.rates.complimentary') }}</label>
                <select id="complimentary_type" name="complimentary_type" class="input"><option value=""></option>
                    @foreach (['comp', 'house_use'] as $t)<option value="{{ $t }}" @selected($v('complimentary_type') === $t)>{{ __('pms.rates.comp_types.'.$t) }}</option>@endforeach</select></div>

            <div><label class="field-label" for="room_transaction_code_id">{{ __('pms.rates.department_code') }} *</label>
                <select id="room_transaction_code_id" name="room_transaction_code_id" class="input" required>
                    @foreach ($codes as $c)<option value="{{ $c->id }}" @selected($sel('room_transaction_code_id', $c->id))>{{ $c->code }}  {{ $c->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="package_id">{{ __('pms.rates.board') }}</label>
                <select id="package_id" name="package_id" class="input"><option value="">{{ __('pms.rates.no_board') }}</option>
                    @foreach ($packages as $pk)<option value="{{ $pk->id }}" @selected($sel('package_id', $pk->id))>{{ $pk->code }} - {{ $pk->name }}</option>@endforeach</select></div>

            <div><label class="field-label" for="weekend_transaction_code_id">{{ __('pms.rates.weekend_department_code') }}</label>
                <select id="weekend_transaction_code_id" name="weekend_transaction_code_id" class="input"><option value=""></option>
                    @foreach ($codes as $c)<option value="{{ $c->id }}" @selected($sel('weekend_transaction_code_id', $c->id))>{{ $c->code }}  {{ $c->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="currency_code">{{ __('pms.rates.currency') }} *</label>
                <select id="currency_code" name="currency_code" class="input" required>
                    @foreach ($currencies as $c)<option value="{{ $c->code }}" @selected($v('currency_code') === $c->code)>{{ $c->code }}  {{ $c->name }}</option>@endforeach</select></div>

            <div class="grid grid-cols-2 gap-2">
                <div><label class="field-label" for="min_advance_days">{{ __('pms.rates.adv_min') }}</label><input id="min_advance_days" name="min_advance_days" value="{{ $v('min_advance_days') }}" class="input" inputmode="numeric"></div>
                <div><label class="field-label" for="max_advance_days">{{ __('pms.rates.adv_max') }}</label><input id="max_advance_days" name="max_advance_days" value="{{ $v('max_advance_days') }}" class="input" inputmode="numeric"></div>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <fieldset class="rounded border border-line p-2"><legend class="px-1 text-[12px] text-muted">{{ __('pms.rates.exchange') }}</legend>
                <label class="flex items-center gap-2"><input type="hidden" name="refresh_exchange_at_checkin" value="0"><input type="checkbox" name="refresh_exchange_at_checkin" value="1" @checked($v('refresh_exchange_at_checkin'))> {{ __('pms.rates.refresh_at_checkin') }}</label>
            </fieldset>
            <fieldset class="rounded border border-line p-2"><legend class="px-1 text-[12px] text-muted">{{ __('pms.rates.weekend_days') }}</legend>
                <div class="grid grid-cols-3 gap-1">
                    @foreach ($days as $i => $d)
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="weekend[]" value="{{ $i }}" @checked(in_array($i, array_map('intval', $weekend), true))> {{ __('pms.rates.days_long.'.$d) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-2 gap-1">
                @foreach (['print_rate', 'is_fixed_rate', 'is_flat_rate', 'is_public'] as $flag)
                    <label class="flex items-center gap-2"><input type="hidden" name="{{ $flag }}" value="0"><input type="checkbox" name="{{ $flag }}" value="1" @checked($v($flag))> {{ __('pms.rates.'.$flag) }}</label>
                @endforeach
                <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate->is_active ?? true))> {{ __('pms.common.active') }}</label>
            </div>
        </div>
    </form>

    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex items-center gap-3 px-3 py-1.5 border-b border-line bg-panel"><b>{{ __('pms.rates.rate_details') }}</b>
                @unless ($rate->exists)<span class="text-[12px] text-muted">{{ __('pms.rates.save_first') }}</span>@endunless</div>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="detail-grid" data-grid data-selected="{{ request('detay', 0) }}">
                    <thead><tr><th>{{ __('pms.rates.from_date') }}</th><th>{{ __('pms.rates.to_date') }}</th><th>{{ __('pms.rates.room_types') }}</th><th>{{ __('pms.rates.rooms') }}</th>
                        <th>{{ __('pms.rates.season') }}</th><th class="text-right">Default</th><th class="text-right">1</th><th class="text-right">2</th><th class="text-right">3</th><th class="text-right">4</th><th class="text-right">CA1</th><th class="text-right">CA2</th></tr></thead>
                    <tbody>
                    @foreach ($details as $d)
                        <tr data-id="{{ $d->id }}" data-url-edit="{{ route('setup.rates.details.edit', [$rate, $d]) }}" data-url-copy="{{ route('setup.rates.details.copy', [$rate, $d]) }}"
                            data-url-destroy="{{ route('setup.rates.details.destroy', [$rate, $d]) }}">
                            <td>{{ $d->date_from->format('d.m.Y') }}</td><td>{{ $d->date_to->format('d.m.Y') }}</td>
                            <td>{{ $d->roomTypes->pluck('code')->implode(', ') }}</td><td>{{ $d->room_numbers }}</td><td>{{ $d->season?->code }}</td>
                            @foreach (['price_default', 'price_1', 'price_2', 'price_3', 'price_4', 'child_band_1', 'child_band_2'] as $f)
                                <td class="text-right tabular-nums">{{ $money($d->{$f}) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($details->isNotEmpty()) hidden @endif><td colspan="12" class="text-center text-muted py-4">{{ __('pms.rates.no_details') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @if ($rate->exists)
                <a href="{{ route('setup.rates.details.create', $rate) }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.rates.new_detail') }} <span class="kbd ml-auto">Alt+N</span></a>
                <a class="btn btn-side" data-needs-selection="detail-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
                <a class="btn btn-side" data-needs-selection="detail-grid" data-action="copy" data-hotkey="alt+c">{{ __('pms.common.copy') }} <span class="kbd ml-auto">Alt+C</span></a>
                <form method="POST" data-confirm="{{ __('pms.rates.detail_delete_confirm') }}">@csrf @method('DELETE')
                    <button class="btn btn-side w-full" data-needs-selection="detail-grid" data-action="destroy" data-hotkey="alt+d delete">{{ __('pms.common.delete') }} <span class="kbd ml-auto">Alt+D</span></button></form>
            @endif
            <button form="rate-form" class="btn btn-primary mt-auto justify-center" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
            <a href="{{ route('setup.rates.index', ['secili' => $rate->id]) }}" class="btn btn-side" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
