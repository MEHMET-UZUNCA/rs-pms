@extends('layouts.app')
@section('title', __('pms.rates.detail_title', ['code' => $rate->code]))
@section('content')
@php
    $date = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y') : '';
    $money = fn ($x) => $x === null ? '' : number_format((float) $x, 2, ',', '.');
    $price = fn (string $f) => old($f, $money($detail->{$f}));
    $sel = fn (string $f, $id) => (string) old($f, $detail->{$f}) === (string) $id;
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $checkedDays = array_map('intval', old('days', collect(range(0, 6))->filter(fn ($i) => ((int) ($detail->weekdays ?? 127)) & (1 << $i))->all()));
    $types = array_map('intval', old('room_types', $roomTypeIds));
    $editing = $detail->exists && ! $copy;
@endphp
<form method="POST" action="{{ $editing ? route('setup.rates.details.update', [$rate, $detail]) : route('setup.rates.details.store', $rate) }}" class="win flex-1">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="win-title"><span>{{ __('pms.rates.detail_title', ['code' => $rate->code]) }} — {{ $rate->name }}</span>
        <span class="font-normal text-brand-100 text-[12px]">{{ $copy ? __('pms.rates.detail_copy') : '' }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-2">
        <section class="win !min-h-fit p-3 grid grid-cols-2 gap-x-3 gap-y-2 content-start">
            <b class="col-span-2">{{ __('pms.rates.rate_details') }}</b>
            <div class="col-span-2"><label class="field-label" for="season_id">{{ __('pms.rates.season') }}</label>
                <select id="season_id" name="season_id" class="input"><option value=""></option>
                    @foreach ($seasons as $s)<option value="{{ $s->id }}" @selected($sel('season_id', $s->id))>{{ $s->code }} {{ $s->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="date_from">{{ __('pms.rates.start_date') }} *</label>
                <input id="date_from" name="date_from" value="{{ old('date_from', $date($detail->date_from)) }}" class="input @error('date_from') input-error @enderror" required autofocus autocomplete="off"></div>
            <div><label class="field-label" for="date_to">{{ __('pms.rates.end_date') }} *</label>
                <input id="date_to" name="date_to" value="{{ old('date_to', $date($detail->date_to)) }}" class="input @error('date_to') input-error @enderror" required autocomplete="off"></div>
            <div><label class="field-label" for="market_segment_id">{{ __('pms.rates.default_market') }}</label>
                <select id="market_segment_id" name="market_segment_id" class="input"><option value=""></option>
                    @foreach ($segments as $s)<option value="{{ $s->id }}" @selected($sel('market_segment_id', $s->id))>{{ $s->code }}  {{ $s->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="source_code_id">{{ __('pms.rates.default_source') }}</label>
                <select id="source_code_id" name="source_code_id" class="input"><option value=""></option>
                    @foreach ($sources as $s)<option value="{{ $s->id }}" @selected($sel('source_code_id', $s->id))>{{ $s->code }}  {{ $s->name }}</option>@endforeach</select></div>
            <div class="col-span-2"><span class="field-label">{{ __('pms.rates.room_types') }} <span class="text-muted">({{ __('pms.rates.room_types_hint') }})</span></span>
                <div class="grid grid-cols-5 gap-1 mt-1" data-room-types>
                    @foreach ($roomTypes as $t)
                        <label class="flex items-center gap-1.5" title="{{ $t->name }}"><input type="checkbox" name="room_types[]" value="{{ $t->id }}" @checked(in_array((int) $t->id, $types, true))> {{ $t->code }}</label>
                    @endforeach
                </div></div>
            <div class="col-span-2"><label class="field-label" for="room_numbers">{{ __('pms.rates.room_numbers') }}</label>
                <input id="room_numbers" name="room_numbers" value="{{ old('room_numbers', $detail->room_numbers) }}" maxlength="250" class="input @error('room_numbers') input-error @enderror" placeholder="1322, 2410"></div>
            <div class="col-span-2"><span class="field-label">{{ __('pms.rates.valid_days') }}</span>
                <div class="flex flex-wrap gap-3 mt-1">
                    @foreach ($days as $i => $d)
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="{{ $i }}" @checked(in_array($i, $checkedDays, true))> {{ __('pms.rates.days.'.$d) }}</label>
                    @endforeach
                </div></div>
        </section>

        <section class="win !min-h-fit p-3 grid gap-3 sm:grid-cols-2 content-start">
            <b class="sm:col-span-2">{{ __('pms.rates.prices') }} <span class="font-normal text-muted text-[12px]">{{ $rate->currency_code }}</span></b>
            <table class="grid-table" data-adult-prices>
                <thead><tr><th>{{ __('pms.rates.no_of_adults') }}</th><th class="text-right">{{ __('pms.rates.price') }}</th><th class="text-right">{{ __('pms.rates.weekend_price') }}</th></tr></thead>
                <tbody>
                @foreach (['default' => 'Default', 1 => '1', 2 => '2', 3 => '3', 4 => '4'] as $n => $label)
                    <tr><td class="font-semibold">{{ $label }}</td>
                        <td><input name="price_{{ $n }}" value="{{ $price('price_'.$n) }}" class="input text-right @error('price_'.$n) input-error @enderror" inputmode="decimal" aria-label="{{ __('pms.rates.price') }} {{ $label }}"></td>
                        <td><input name="weekend_price_{{ $n }}" value="{{ $price('weekend_price_'.$n) }}" class="input text-right" inputmode="decimal" aria-label="{{ __('pms.rates.weekend_price') }} {{ $label }}"></td></tr>
                @endforeach
                <tr><td>{{ __('pms.rates.extra_adult') }}</td><td><input name="extra_adult" value="{{ $price('extra_adult') }}" class="input text-right" inputmode="decimal"></td><td></td></tr>
                <tr><td>{{ __('pms.rates.extra_bed') }}</td><td><input name="extra_bed" value="{{ $price('extra_bed') }}" class="input text-right" inputmode="decimal"></td><td></td></tr>
                </tbody>
            </table>
            <table class="grid-table self-start" data-child-prices>
                <thead><tr><th>{{ __('pms.rates.child_age') }}</th><th class="text-right">{{ __('pms.rates.price') }}</th><th class="text-right">{{ __('pms.rates.weekend_price') }}</th></tr></thead>
                <tbody>
                @foreach ($bands as $b)
                    <tr><td class="font-semibold" title="{{ $b->age_from }}–{{ $b->age_to }}">{{ $b->name }} <span class="text-muted text-[11px]">{{ $b->age_from }}–{{ $b->age_to }}</span></td>
                        <td><input name="child_band_{{ $b->band }}" value="{{ $price('child_band_'.$b->band) }}" class="input text-right" inputmode="decimal" aria-label="{{ $b->name }}"></td>
                        <td><input name="weekend_child_band_{{ $b->band }}" value="{{ $price('weekend_child_band_'.$b->band) }}" class="input text-right" inputmode="decimal" aria-label="{{ $b->name }} {{ __('pms.rates.weekend_price') }}"></td></tr>
                @endforeach
                </tbody>
            </table>
            <p class="sm:col-span-2 text-[12px] text-muted">{{ __('pms.rates.prices_hint') }}</p>
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.rates.edit', ['rate' => $rate, 'detay' => $detail->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
