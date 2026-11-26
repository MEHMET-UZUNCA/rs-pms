@extends('layouts.app')
@section('title', __('pms.menu.room_history'))
@section('content')
@php $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : ''; $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.menu.room_history') }}@if ($room) — {{ $room->number }} {{ $room->type_code }}@endif</span>
        @if ($room)<span class="font-normal text-brand-100 text-[12px]">{{ $room->type_name }} · {{ $room->description }}</span>@endif</div>
    <form method="GET" class="flex flex-wrap items-end gap-3 px-3 py-2 border-b border-line">
        <label class="flex flex-col"><span class="field-label">{{ __('pms.lf.room') }}</span><input name="oda" value="{{ $number }}" class="input w-28" autofocus autocomplete="off" required></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span><input name="bas" value="{{ $from->format('d.m.Y') }}" class="input w-32" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.to') }}</span><input name="bit" value="{{ $to->format('d.m.Y') }}" class="input w-32" autocomplete="off"></label>
        <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
        @if ($number !== '' && ! $room)<span class="text-danger">{{ __('pms.history.not_found', ['room' => $number]) }}</span>@endif
        @if ($room)<span class="ml-auto text-[12px] text-muted">{{ __('pms.history.now') }}: {{ __('pms.history.fo.'.$room->fo_status) }} · {{ strtoupper($room->hk_status) }}</span>@endif
    </form>
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-2">
        @if ($room)
            <section class="win !min-h-fit p-3 xl:col-span-2" data-section="guests">
                <b>{{ __('pms.history.guests') }}</b> <span class="text-muted text-[12px]">{{ $guests->count() }}</span>
                <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.res.conf_no') }}</th><th>{{ __('pms.res.guest') }}</th><th>{{ __('pms.res.arrival') }}</th><th>{{ __('pms.res.departure') }}</th>
                    <th class="text-right">{{ __('pms.history.nights_here') }}</th><th>{{ __('pms.res.pax') }}</th><th>{{ __('pms.res.rate_code') }}</th><th class="text-right">{{ __('pms.history.revenue') }}</th><th>{{ __('pms.res.status') }}</th></tr></thead>
                    <tbody>
                    @forelse ($guests as $g)
                        <tr><td>@can('permission', 'reservations.edit')<a class="text-brand-600 hover:underline" href="{{ route('reservations.edit', $g->id) }}">{{ $g->confirmation_number }}</a>@else{{ $g->confirmation_number }}@endcan</td>
                            <td>{{ $g->display_name }}</td><td>{{ $d($g->arrival_date) }}</td><td>{{ $d($g->departure_date) }}</td><td class="text-right">{{ $g->nights_here }}</td>
                            <td>{{ $g->adults }}{{ $g->children ? '+'.$g->children : '' }}</td><td>{{ $g->rate_code }}</td><td class="text-right tabular-nums">{{ $money($g->revenue) }}</td>
                            <td>{{ __('pms.res.statuses.'.$g->status) }}</td></tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">{{ __('pms.common.no_records') }}</td></tr>
                    @endforelse
                    </tbody></table>
            </section>
            <section class="win !min-h-fit p-3" data-section="statuses">
                <b>{{ __('pms.history.status_changes') }}</b>
                <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.history.change') }}</th><th>{{ __('pms.history.source') }}</th><th>{{ __('pms.common.user') }}</th></tr></thead>
                    <tbody>
                    @forelse ($statuses as $s)
                        <tr><td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ $s->field === 'hk_flag' ? __('pms.history.flag') : '' }} {{ strtoupper((string) $s->from_value) ?: '—' }} → {{ strtoupper($s->to_value) }}</td><td>{{ $s->source }}</td><td>{{ $s->username }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">{{ __('pms.common.no_records') }}</td></tr>
                    @endforelse
                    </tbody></table>
            </section>
            <div class="flex flex-col gap-3">
                <section class="win !min-h-fit p-3" data-section="outages">
                    <b>{{ __('pms.ooo.title') }}</b>
                    <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.ooo.from') }}</th><th>{{ __('pms.ooo.to_exclusive') }}</th><th>{{ __('pms.ooo.reason') }}</th><th>{{ __('pms.ooo.released') }}</th></tr></thead>
                        <tbody>
                        @forelse ($outages as $o)
                            <tr><td>{{ strtoupper($o->type) }}</td><td>{{ $d($o->date_from) }}</td><td>{{ $d($o->date_to) }}</td><td>{{ $o->reason }} {{ $o->note }}</td><td>{{ $d($o->released_at) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">{{ __('pms.common.no_records') }}</td></tr>
                        @endforelse
                        </tbody></table>
                </section>
                <section class="win !min-h-fit p-3" data-section="moves">
                    <b>{{ __('pms.hs.room_moves') }}</b>
                    <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.history.move') }}</th><th>{{ __('pms.res.guest') }}</th><th>{{ __('pms.common.user') }}</th></tr></thead>
                        <tbody>
                        @forelse ($moves as $m)
                            <tr><td>{{ $d($m->business_date) }}</td><td>{{ $m->from_room ?? '—' }} → {{ $m->to_room }}</td><td>{{ $m->display_name }}</td><td>{{ $m->username }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">{{ __('pms.common.no_records') }}</td></tr>
                        @endforelse
                        </tbody></table>
                </section>
            </div>
        @else
            <p class="text-muted">{{ __('pms.history.hint') }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</div>
@endsection
