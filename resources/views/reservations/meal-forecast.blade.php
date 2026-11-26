@extends('layouts.app')
@section('title', __('pms.meal.title'))
@section('content')
@php $total = fn (string $meal) => collect($rows)->sum(fn ($d) => $d[$meal]['adults'] + $d[$meal]['children']); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.meal.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.meal.subtitle') }}</span></div>
    <form method="GET" class="flex flex-wrap items-end gap-3 px-3 py-2 border-b border-line">
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span><input name="bas" value="{{ $from->format('d.m.Y') }}" class="input w-32" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.meal.days') }}</span>
            <select name="gun" class="input w-24">@foreach ([1, 3, 7, 14, 31] as $n)<option value="{{ $n }}" @selected($days === $n)>{{ $n }}</option>@endforeach</select></label>
        <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
        <span class="text-[12px] text-muted">{{ __('pms.meal.rule_hint') }}</span>
    </form>
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <table class="grid-table" data-meal-forecast>
            <thead>
                <tr><th rowspan="2">{{ __('pms.common.date') }}</th>@foreach (['breakfast', 'lunch', 'dinner'] as $m)<th colspan="3" class="text-center border-l border-line">{{ __('pms.options.meals.'.$m) }}</th>@endforeach</tr>
                <tr>@foreach (['breakfast', 'lunch', 'dinner'] as $m)<th class="text-right border-l border-line">{{ __('pms.meal.adults') }}</th><th class="text-right">{{ __('pms.meal.children') }}</th><th class="text-right">{{ __('pms.meal.total') }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach ($rows as $date => $day)
                <tr data-date="{{ $date }}"><td class="font-semibold whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y D') }}</td>
                    @foreach (['breakfast', 'lunch', 'dinner'] as $m)
                        <td class="text-right tabular-nums border-l border-line">{{ $day[$m]['adults'] }}</td><td class="text-right tabular-nums">{{ $day[$m]['children'] }}</td>
                        <td class="text-right tabular-nums font-semibold" data-meal="{{ $m }}">{{ $day[$m]['adults'] + $day[$m]['children'] }}</td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="font-semibold"><td>{{ __('pms.meal.total') }}</td>@foreach (['breakfast', 'lunch', 'dinner'] as $m)<td colspan="3" class="text-right tabular-nums border-l border-line">{{ $total($m) }}</td>@endforeach</tr>
            </tbody>
        </table>
        <section class="win !min-h-fit p-3 self-start">
            <b>{{ __('pms.meal.by_board') }}</b>
            <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.rates.board') }}</th><th class="text-right">{{ __('pms.meal.room_nights') }}</th><th class="text-right">{{ __('pms.meal.adults') }}</th><th class="text-right">{{ __('pms.meal.children') }}</th></tr></thead>
                <tbody>@foreach ($boards as $b)<tr><td>{{ $b->board === '-' ? __('pms.rates.no_board') : $b->board }}</td><td class="text-right">{{ $b->room_nights }}</td><td class="text-right">{{ $b->adults }}</td><td class="text-right">{{ $b->children }}</td></tr>@endforeach</tbody></table>
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</div>
@endsection
