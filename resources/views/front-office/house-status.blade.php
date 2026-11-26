@extends('layouts.app')
@section('title', __('pms.hs.title'))
@section('content')
@php
    $m = $s['movement']; $hk = $s['housekeeping']; $e = $s['end_of_day'];
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
    $can = fn (string $p) => Gate::allows('permission', $p);
    $nav = fn (string $f) => $can('reservations.view') ? route('reservations.navigator', ['f' => $f]) : null;
    $hkLink = fn (?string $f) => $can('housekeeping.view') ? route('housekeeping.index', $f ? ['f' => $f] : []) : null;
    $cell = 'w-20 text-right tabular-nums border border-line rounded px-2 py-0.5 bg-surface';
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.hs.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.business_date') }} {{ \Illuminate\Support\Carbon::parse($s['business_date'])->format('d.m.Y') }}</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 lg:grid-cols-2 content-start text-[13px]">
        <div class="flex flex-col gap-3">
            <section class="win !min-h-fit p-3" data-hs="total">
                <b>{{ __('pms.hs.total') }}</b>
                <table class="mt-1"><thead><tr><th></th><th class="text-right text-[12px] font-normal text-muted px-1">{{ __('pms.hs.rooms') }}</th></tr></thead><tbody>
                    <tr><td class="pr-4 py-0.5">{{ __('pms.hs.total_number') }}</td><td><div class="{{ $cell }}" data-v="total">{{ $s['total']['total'] }}</div></td></tr>
                    <tr><td class="pr-4 py-0.5">{{ __('pms.hs.total_to_sell') }}</td><td><div class="{{ $cell }}" data-v="to_sell">{{ $s['total']['to_sell'] }}</div></td></tr>
                </tbody></table>
            </section>
            <section class="win !min-h-fit p-3" data-hs="movement">
                <b>{{ __('pms.hs.movement') }}</b>
                <table class="mt-1"><thead><tr><th></th>@foreach (['rooms', 'pax', 'children'] as $h)<th class="text-right text-[12px] font-normal text-muted px-1">{{ __('pms.hs.'.$h) }}</th>@endforeach<th></th></tr></thead><tbody>
                    @foreach (['expected_arrivals' => $nav('gelecek'), 'actual_arrivals' => $nav('iceride'), 'arrival_rooms_assigned' => $nav('gelecek'),
                        'expected_departures' => $nav('gidecek'), 'actual_departures' => $nav('cikmis'), 'day_rooms' => null, 'walk_ins' => null,
                        'same_day_reservations' => null, 'room_moves' => null, 'early_departures' => $nav('cikmis')] as $key => $link)
                        <tr data-row="{{ $key }}"><td class="pr-4 py-0.5">{{ __('pms.hs.'.$key) }}</td>
                            @foreach (['rooms', 'pax', 'children'] as $c)<td class="px-0.5"><div class="{{ $cell }}">{{ $m[$key][$c] }}</div></td>@endforeach
                            <td class="pl-1">@if ($link)<a href="{{ $link }}" class="text-brand-600" title="{{ __('pms.hs.drill') }}">⬇</a>@endif</td></tr>
                    @endforeach
                    <tr data-row="current_occupancy"><td class="pr-4 pt-3">{{ __('pms.hs.current_occupancy') }}</td>
                        @foreach (['rooms', 'pax', 'children'] as $c)<td class="px-0.5 pt-3"><div class="{{ $cell }}">{{ $m['current_occupancy'][$c] }}</div></td>@endforeach<td></td></tr>
                    <tr><td></td><td class="px-0.5"><div class="{{ $cell }}" data-v="occupancy_pct">{{ number_format($m['current_occupancy']['pct'], 0) }}</div></td><td class="text-muted">%</td></tr>
                </tbody></table>
            </section>
            <form method="GET" class="flex flex-col gap-1 px-1">
                <label class="flex items-center gap-2"><input type="checkbox" name="cocuk" value="1" @checked($s['options']['children_in_pax']) onchange="this.form.submit()"> {{ __('pms.hs.add_children') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="gunubirlik" value="1" @checked($s['options']['include_day_use']) onchange="this.form.submit()"> {{ __('pms.hs.include_day_use') }}</label>
            </form>
        </div>

        <div class="flex flex-col gap-3">
            <section class="win !min-h-fit p-3" data-hs="housekeeping">
                <b>{{ __('pms.hs.housekeeping') }}</b>
                <table class="mt-1"><thead><tr><th></th>@foreach (['total', 'occupied', 'vacant'] as $h)<th class="text-right text-[12px] font-normal text-muted px-1">{{ __('pms.hs.'.$h) }}</th>@endforeach<th></th></tr></thead><tbody>
                    @foreach (['clean' => 'vc', 'dirty' => 'vd'] as $key => $f)
                        <tr data-row="{{ $key }}"><td class="pr-4 py-0.5">{{ __('pms.hs.'.$key.'_rooms') }}</td>
                            @foreach (['total', 'occupied', 'vacant'] as $c)<td class="px-0.5"><div class="{{ $cell }}">{{ $hk[$key][$c] }}</div></td>@endforeach
                            <td class="pl-1">@if ($hkLink($f))<a href="{{ $hkLink($f) }}" class="text-brand-600" title="{{ __('pms.hs.drill') }}">⬇</a>@endif</td></tr>
                    @endforeach
                    <tr data-row="hk_total"><td class="pr-4 py-0.5">{{ __('pms.hs.total') }}</td><td></td>
                        <td class="px-0.5"><div class="{{ $cell }}">{{ $hk['total']['occupied'] }}</div></td><td class="px-0.5"><div class="{{ $cell }}">{{ $hk['total']['vacant'] }}</div></td><td></td></tr>
                    @foreach (['out_of_order', 'out_of_service'] as $key)
                        <tr data-row="{{ $key }}"><td class="pr-4 pt-3">{{ __('pms.hs.'.$key) }}</td>
                            @foreach (['total', 'occupied', 'vacant'] as $c)<td class="px-0.5 pt-3"><div class="{{ $cell }}">{{ $hk[$key][$c] }}</div></td>@endforeach
                            <td class="pl-1 pt-3">@if ($hkLink('ooo'))<a href="{{ $hkLink('ooo') }}" class="text-brand-600" title="{{ __('pms.hs.drill') }}">⬇</a>@endif</td></tr>
                    @endforeach
                    <tr data-row="queue"><td class="pr-4 py-0.5">{{ __('pms.hs.rooms_in_queue') }}</td><td></td><td></td><td class="px-0.5"><div class="{{ $cell }}">{{ $hk['queue'] }}</div></td><td></td></tr>
                </tbody></table>
            </section>
            <section class="win !min-h-fit p-3" data-hs="end_of_day">
                <b>{{ __('pms.hs.end_of_day') }}</b>
                <table class="mt-1"><tbody>
                    <tr data-row="adr_gross"><td class="pr-4 py-0.5">{{ __('pms.hs.adr_gross') }}</td><td class="px-0.5"><div class="{{ $cell }} !w-28">{{ $money($e['adr_gross']) }}</div></td><td class="px-0.5"><div class="{{ $cell }} !w-28" title="{{ __('pms.hs.per_person') }}">{{ $money($e['adr_gross_pp']) }}</div></td></tr>
                    <tr data-row="adr_net"><td class="pr-4 py-0.5">{{ __('pms.hs.adr_net') }}</td><td class="px-0.5"><div class="{{ $cell }} !w-28">{{ $money($e['adr_net']) }}</div></td><td class="px-0.5"><div class="{{ $cell }} !w-28" title="{{ __('pms.hs.per_person') }}">{{ $money($e['adr_net_pp']) }}</div></td></tr>
                    <tr data-row="available_tonight"><td class="pr-4 py-0.5">{{ __('pms.hs.available_tonight') }}</td><td class="px-0.5"><div class="{{ $cell }} !w-28">{{ $e['available_tonight'] }}</div></td><td></td></tr>
                </tbody></table>
                <table class="mt-2"><thead><tr><th></th>@foreach (['rooms', 'pax', 'children'] as $h)<th class="text-right text-[12px] font-normal text-muted px-1">{{ __('pms.hs.'.$h) }}</th>@endforeach</tr></thead><tbody>
                    @foreach (['allotments', 'groups', 'group_and_allotment', 'individuals', 'in_house_tonight'] as $key)
                        <tr data-row="{{ $key }}"><td class="pr-4 py-0.5">{{ __('pms.hs.'.$key) }}</td>
                            @foreach (['rooms', 'pax', 'children'] as $c)<td class="px-0.5"><div class="{{ $cell }}">{{ $e[$key][$c] }}</div></td>@endforeach</tr>
                    @endforeach
                </tbody></table>
                <p class="text-[12px] text-muted mt-2">{{ __('pms.hs.adr_hint') }}</p>
            </section>
        </div>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <span class="text-[12px] text-muted">{{ __('pms.hs.next_update') }} <span data-countdown>300</span> s</span>
        <a href="{{ request()->fullUrl() }}" class="btn ml-auto" data-hotkey="f5" data-refresh>{{ __('pms.hs.refresh') }} <span class="kbd">F5</span></a>
        <a href="{{ route('home') }}" class="btn btn-primary" data-hotkey="escape f10">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">Esc</span></a>
    </div>
</div>
<script>
    // Fidelio gibi 5 dakikada bir kendini yeniler
    (() => {
        let left = 300; const el = document.querySelector('[data-countdown]');
        setInterval(() => { left -= 1; if (el) el.textContent = left; if (left <= 0) document.querySelector('[data-refresh]').click(); }, 1000);
    })();
</script>
@endsection
