@extends('layouts.app')
@section('title', $block->code)
@section('content')
@php
    $canEdit = Gate::allows('permission', 'reservations.edit');
    $canCheckIn = Gate::allows('permission', 'reservations.checkin');
    $d = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y') : '';
    $open = $block->status !== 'cancelled';
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ $block->code }} · {{ $block->name }}</span>
        <span class="font-normal text-brand-100 text-[12px]">{{ __('pms.block.types.'.$block->type) }} · {{ __('pms.block.statuses.'.$block->status) }}</span></div>
    <div class="flex flex-1 min-h-0">
        <div class="flex-1 min-w-0 overflow-auto p-3 flex flex-col gap-3">
            <section class="win !min-h-fit p-3 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-x-4 gap-y-1 text-[13px]">
                <div><div class="text-muted text-[12px]">{{ __('pms.block.agent') }}</div><div class="font-semibold">{{ $agent }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.block.start') }}</div><div>{{ $d($block->start_date) }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.block.end') }}</div><div>{{ $d($block->end_date) }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.res.rate_code') }}</div><div>{{ $rate?->code }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.block.cutoff') }}</div><div @class(['text-danger font-semibold' => $block->cutoff_date && $block->cutoff_date->lte($bd) && $block->status === 'tentative'])>{{ $d($block->cutoff_date) }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.block.release_days') }}</div><div>{{ $block->release_days ?: '—' }}</div></div>
                <div><div class="text-muted text-[12px]">{{ __('pms.block.contract') }}</div><div>{{ $block->contract_number }}</div></div>
                <div class="col-span-2"><div class="text-muted text-[12px]">{{ __('pms.block.master') }}</div>
                    <div>@if ($master)<a class="text-brand-600 hover:underline font-semibold" href="{{ route('billing.account', $master) }}">{{ $master->folio_number }} {{ $master->name }}</a> · {{ number_format((float) $master->balance, 2, ',', '.') }}@else<span class="text-muted">{{ __('pms.block.no_master') }}</span>@endif</div></div>
                @if ($block->notes)<div class="col-span-full text-muted">{{ $block->notes }}</div>@endif
            </section>

            <div class="grid gap-3 xl:grid-cols-[2fr_3fr]">
                <section class="win !min-h-fit p-3 min-w-0">
                    <b>{{ __('pms.block.rooms_per_night') }}</b>
                    <table class="grid-table mt-1" data-block-summary><thead><tr><th>{{ __('pms.res.room_type') }}</th><th class="text-right">{{ __('pms.block.allotted') }}</th>
                        <th class="text-right">{{ __('pms.block.picked_up') }}</th><th class="text-right">{{ __('pms.block.released') }}</th><th class="text-right">{{ __('pms.block.remaining') }}</th></tr></thead><tbody>
                        @forelse ($summary as $s)
                            <tr><td><b>{{ $s->code }}</b> {{ $s->name }}</td><td class="text-right tabular-nums">{{ $s->allotted }}</td><td class="text-right tabular-nums">{{ $s->picked_up }}</td>
                                <td class="text-right tabular-nums text-muted">{{ $s->released ?: '' }}</td><td class="text-right tabular-nums font-semibold" data-remaining="{{ $s->code }}">{{ $s->remaining }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">{{ __('pms.block.no_rooms') }}</td></tr>
                        @endforelse
                    </tbody></table>
                </section>

                @if ($canEdit && $open && $summary->isNotEmpty())
                    <form method="POST" action="{{ route('blocks.pick-up', $block) }}" class="win !min-h-fit p-3 grid grid-cols-2 md:grid-cols-4 gap-2 content-start min-w-0">
                        @csrf
                        <b class="col-span-full">{{ __('pms.block.pick_up') }}</b>
                        <div><label class="field-label" for="pu-type">{{ __('pms.res.room_type') }}</label>
                            <select id="pu-type" name="room_type_id" class="input">@foreach ($summary as $s)<option value="{{ $s->room_type_id }}" @selected((int) old('room_type_id') === (int) $s->room_type_id)>{{ $s->code }} ({{ $s->remaining }})</option>@endforeach</select></div>
                        <div><label class="field-label" for="pu-count">{{ __('pms.block.rooms') }}</label><input id="pu-count" name="count" type="number" min="0" max="200" value="{{ old('count') }}" class="input text-right"></div>
                        <div><label class="field-label" for="pu-adults">{{ __('pms.card.adults') }}</label><input id="pu-adults" name="adults" type="number" min="1" max="6" value="{{ old('adults', 2) }}" class="input text-right"></div>
                        <div><label class="field-label" for="pu-type2">{{ __('pms.card.reservation_type') }}</label>
                            <select id="pu-type2" name="reservation_type_id" class="input">@foreach ($types as $t)<option value="{{ $t->id }}" @selected((int) old('reservation_type_id') === (int) $t->id)>{{ $t->code }} {{ $t->name }}</option>@endforeach</select></div>
                        <div><label class="field-label" for="pu-arr">{{ __('pms.res.arrival') }}</label><input id="pu-arr" name="arrival_date" value="{{ old('arrival_date', $d($block->start_date)) }}" class="input" autocomplete="off"></div>
                        <div><label class="field-label" for="pu-dep">{{ __('pms.res.departure') }}</label><input id="pu-dep" name="departure_date" value="{{ old('departure_date', $d($block->end_date)) }}" class="input" autocomplete="off"></div>
                        <div class="col-span-full"><label class="field-label" for="pu-names">{{ __('pms.block.rooming_list') }}</label>
                            <textarea id="pu-names" name="names" rows="3" class="input !h-auto py-1" placeholder="{{ __('pms.block.rooming_hint') }}">{{ old('names') }}</textarea></div>
                        <div class="col-span-full flex"><button class="btn btn-primary ml-auto" data-hotkey="alt+p">{{ __('pms.block.create_reservations') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">Alt+P</span></button></div>
                    </form>
                @endif
            </div>

            @if ($grid->isNotEmpty())
                <form method="POST" action="{{ route('blocks.inventory', $block) }}" class="win !min-h-fit p-3 min-w-0" data-block-grid>
                    @csrf @method('PUT')
                    @php $dates = $grid->first()->keys(); @endphp
                    <div class="flex items-center gap-2 mb-1"><b>{{ __('pms.block.grid') }}</b><span class="text-[12px] text-muted">{{ __('pms.block.grid_hint') }}</span>
                        @if ($canEdit && $open)<button class="btn btn-primary ml-auto" data-hotkey="alt+q">{{ __('pms.common.save') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">Alt+Q</span></button>@endif</div>
                    <div class="overflow-x-auto"><table class="grid-table text-[12.5px]">
                        <thead><tr><th class="sticky left-0 bg-panel">{{ __('pms.res.room_type') }}</th>
                            @foreach ($dates as $date)<th class="text-center whitespace-nowrap px-1">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m') }}<br><span class="font-normal text-muted">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D') }}</span></th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($grid as $typeId => $days)
                            <tr><td class="sticky left-0 bg-panel font-semibold">{{ $summary->firstWhere('room_type_id', $typeId)?->code }}</td>
                                @foreach ($dates as $date)
                                    @php $c = $days[$date] ?? null; @endphp
                                    <td class="text-center px-1">@if ($c)
                                        <input name="q[{{ $typeId }}][{{ $date }}]" value="{{ $c->allotted }}" inputmode="numeric" class="input w-12 h-7 text-center px-1" @disabled(! $canEdit || ! $open || $date < $bd->toDateString()) aria-label="{{ $date }}">
                                        <div class="text-[11px] text-muted">{{ $c->picked_up }}@if ($c->released) · <span class="text-danger" title="{{ __('pms.block.released') }}">−{{ $c->released }}</span>@endif</div>
                                    @endif</td>
                                @endforeach</tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </form>
            @endif

            <form method="POST" action="{{ route('blocks.bulk', $block) }}" class="win !min-h-fit p-3 min-w-0" id="bulk-form">
                @csrf
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <b>{{ __('pms.block.members') }} <span class="font-normal text-muted">({{ $members->count() }})</span></b>
                    @if ($canEdit)
                        <span class="ml-auto"></span>
                        <button name="action" value="assign" class="btn" data-hotkey="alt+a">{{ __('pms.block.auto_assign') }} <span class="kbd">Alt+A</span></button>
                        @if ($canCheckIn)<button name="action" value="check_in" class="btn" data-hotkey="alt+g">{{ __('pms.res.check_in') }} <span class="kbd">Alt+G</span></button>@endif
                        @can('permission', 'reservations.checkout')<button name="action" value="check_out" class="btn" data-hotkey="alt+o">{{ __('pms.res.check_out') }} <span class="kbd">Alt+O</span></button>@endcan
                        <button name="action" value="route" class="btn" data-hotkey="alt+m">{{ __('pms.block.route_master') }} <span class="kbd">Alt+M</span></button>
                        <button name="action" value="unroute" class="btn">{{ __('pms.block.unroute') }}</button>
                    @endif
                </div>
                <div class="overflow-x-auto"><table class="grid-table min-w-[44rem]">
                    <thead><tr><th class="w-8"><input type="checkbox" data-check-all aria-label="{{ __('pms.block.select_all') }}"></th><th>{{ __('pms.res.conf_no') }}</th><th>{{ __('pms.prof.name') }}</th>
                        <th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.card.room') }}</th><th>{{ __('pms.res.arrival') }}</th><th>{{ __('pms.res.departure') }}</th>
                        <th class="text-right">{{ __('pms.dash.pax_short') }}</th><th>{{ __('pms.res.status') }}</th><th>{{ __('pms.block.billing') }}</th></tr></thead>
                    <tbody>
                    @forelse ($members as $m)
                        <tr @class(['is-inactive' => in_array($m->status, ['cancelled', 'no_show'], true)])>
                            <td><input type="checkbox" name="ids[]" value="{{ $m->id }}" aria-label="{{ $m->display_name }}"></td>
                            <td><a class="text-brand-600 hover:underline" href="{{ route('reservations.edit', $m->id) }}">{{ $m->confirmation_number }}</a></td>
                            <td class="font-semibold">{{ $m->display_name }}</td><td>{{ $m->room_type }}</td>
                            <td>{{ $m->room ?? '—' }}@if ($m->room && in_array($m->hk_status, ['dirty', 'pickup'], true) && in_array($m->status, ['confirmed', 'tentative'], true)) <span class="text-[11px] text-danger">{{ __('pms.hk.hk_status.'.$m->hk_status) }}</span>@endif</td>
                            <td>{{ $d($m->arrival_date) }}</td><td>{{ $d($m->departure_date) }}</td><td class="text-right">{{ $m->adults }}@if ($m->children)+{{ $m->children }}@endif</td>
                            <td>{{ __('pms.res.statuses.'.$m->status) }}</td>
                            <td class="text-[12px]">{{ isset($routed[$m->id]) ? __('pms.block.to_master') : __('pms.block.own_folio') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">{{ __('pms.block.no_members') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </form>
        </div>
        <aside class="w-44 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @if ($canEdit)<a href="{{ route('blocks.edit', $block) }}" class="btn btn-side" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>@endif
            <a href="{{ route('blocks.index') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
