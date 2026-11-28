@extends('layouts.app')
@section('title', __('pms.hkscreen.title'))
@php
    $hkClass = ['clean' => 'bg-ok/15 text-ok', 'inspected' => 'bg-brand-100 text-brand-700', 'dirty' => 'bg-danger/10 text-danger', 'pickup' => 'bg-amber-100 text-amber-800'];
    $codeOf = $codes->where('kind', 'status')->keyBy('maps_to');
    $q = fn (array $over) => route('housekeeping.index', array_filter(array_merge(['f' => $filter, 'kat' => $floor ?: null, 'blok' => $block ?: null], $over)));
    $canStatus = Gate::allows('permission', 'housekeeping.status');
    $canOutage = Gate::allows('permission', 'housekeeping.outage');
@endphp
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.hkscreen.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.res.business_date_title', ['date' => $businessDate->format('d.m.Y')]) }}</span></div>

    <nav class="flex flex-wrap gap-1.5 px-3 py-2 border-b border-line" aria-label="Filters">
        <a href="{{ $q(['f' => null]) }}" @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full border text-[13px]', 'bg-brand-600 border-brand-600 text-white' => ! $filter, 'bg-white border-line hover:bg-brand-50' => $filter])>{{ __('pms.common.all') }}</a>
        @foreach (\App\Http\Controllers\Housekeeping\HousekeepingController::FILTERS as $key => $countKey)
            <a href="{{ $q(['f' => $key]) }}" data-hotkey="alt+{{ $loop->iteration }}" @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full border text-[13px] whitespace-nowrap',
                'bg-brand-600 border-brand-600 text-white' => $filter === $key, 'bg-white border-line hover:bg-brand-50' => $filter !== $key])>
                {{ __('pms.hkscreen.filters.'.$key) }} <b data-count="{{ $countKey }}">{{ $counts[$countKey] }}</b> <span class="text-[11px] opacity-70">Alt+{{ $loop->iteration }}</span>
            </a>
        @endforeach
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="f" value="{{ $filter }}">
            <select name="blok" class="input w-32" onchange="this.form.submit()"><option value="">{{ __('pms.hkscreen.all_blocks') }}</option>@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected($block === (int) $b->id)>{{ $b->name }}</option>@endforeach</select>
            <select name="kat" class="input w-28" onchange="this.form.submit()"><option value="">{{ __('pms.rack.all_floors') }}</option>@foreach ($floors as $f)<option value="{{ $f->id }}" @selected($floor === (int) $f->id)>{{ $f->name }}</option>@endforeach</select>
        </form>
    </nav>

    <form method="POST" class="flex flex-1 min-h-0" id="hk-form">
        @csrf
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table">
                    <thead><tr>
                        <th class="w-8"><input type="checkbox" data-check-all aria-label="{{ __('pms.common.all') }}"></th>
                        <th>{{ __('pms.res.room') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.hkscreen.floor') }}</th>
                        <th>{{ __('pms.hkscreen.fo') }}</th><th>{{ __('pms.hkscreen.hk') }}</th><th>{{ __('pms.hkscreen.flag') }}</th><th>{{ __('pms.hkscreen.service') }}</th>
                        <th>{{ __('pms.res.guest') }}</th><th>{{ __('pms.hkscreen.today') }}</th><th>{{ __('pms.hkscreen.changed') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rooms as $r)
                        <tr data-room="{{ $r->number }}" @class(['is-selected-row'])>
                            <td><input type="checkbox" name="rooms[]" value="{{ $r->id }}" aria-label="{{ $r->number }}"></td>
                            <td class="font-semibold">{{ $r->number }}</td><td>{{ $r->type }}</td><td>{{ $r->block }}{{ $r->floor }}</td>
                            <td>{{ __('pms.hk.fo_status.'.$r->fo_status) }}</td>
                            <td><span class="px-1.5 rounded text-[12px] {{ $hkClass[$r->hk_status] ?? '' }}" data-hk="{{ $r->hk_status }}">{{ $codeOf[$r->hk_status]->code ?? '' }} · {{ __('pms.hk.hk_status.'.$r->hk_status) }}</span></td>
                            <td>@if ($r->hk_flag)<span class="px-1.5 rounded text-[12px] bg-slate-200">{{ $r->hk_flag }} · {{ $codes->firstWhere('code', $r->hk_flag)?->name }}</span>@endif</td>
                            <td>
                                @if ($r->outage)
                                    <span class="text-danger font-semibold">{{ $r->outage->type }}</span>
                                    <span class="text-[12px] text-muted">{{ \Illuminate\Support\Carbon::parse($r->outage->date_from)->format('d.m') }}–{{ \Illuminate\Support\Carbon::parse($r->outage->date_to)->format('d.m') }} {{ $r->outage->note }}</span>
                                    @if ($canOutage)<button formaction="{{ route('housekeeping.release', $r->outage->id) }}" class="text-brand-600 hover:underline text-[12px] ml-1">{{ __('pms.hkscreen.release') }}</button>@endif
                                @endif
                            </td>
                            <td>{{ $r->guest }}@if ($r->guest) <span class="text-muted text-[12px]">{{ $r->adults }}@if ($r->children)+{{ $r->children }}@endif</span>@endif</td>
                            <td class="text-[12px]">
                                @if ($r->departing)<span class="px-1.5 rounded bg-amber-100 text-amber-800">{{ __('pms.hkscreen.departure') }}</span>@endif
                                @if ($r->arrival)<span class="px-1.5 rounded bg-brand-100 text-brand-700" title="{{ $r->arrival }}">{{ __('pms.hkscreen.arrival') }}</span>@endif
                            </td>
                            <td class="text-[12px] text-muted">{{ $r->hk_status_changed_at ? \Illuminate\Support\Carbon::parse($r->hk_status_changed_at)->format('d.m H:i') : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-1 border-t border-line text-[12px] text-muted">{{ __('pms.common.records', ['count' => $rooms->count()]) }}</div>
        </section>

        <aside class="w-56 shrink-0 border-l border-line p-2 flex flex-col gap-1.5 overflow-auto">
            @if ($canStatus)
                <div class="text-[12px] text-muted">{{ __('pms.hkscreen.set_status') }}</div>
                @foreach ($codes->where('kind', 'status') as $c)
                    <button name="code" value="{{ $c->code }}" formaction="{{ route('housekeeping.status') }}" class="btn btn-side">{{ $c->code }} · {{ $c->name }}</button>
                @endforeach
                <div class="text-[12px] text-muted mt-2">{{ __('pms.hkscreen.set_flag') }}</div>
                <div class="grid grid-cols-2 gap-1">
                    @foreach ($codes->where('kind', 'flag') as $c)
                        <button name="code" value="{{ $c->code }}" formaction="{{ route('housekeeping.status') }}" class="btn !px-2" title="{{ $c->name }}">{{ $c->code }}</button>
                    @endforeach
                    <button name="code" value="CLEAR" formaction="{{ route('housekeeping.status') }}" class="btn !px-2">{{ __('pms.hkscreen.clear_flag') }}</button>
                </div>
            @endif
            @if ($canOutage)
                <fieldset class="border border-line rounded p-2 mt-2 flex flex-col gap-1.5">
                    <legend class="px-1 text-[12px] text-muted">OOO / OOS</legend>
                    <select name="type" class="input"><option value="OOO">{{ __('pms.hk.service_status.out_of_order') }}</option><option value="OOS">{{ __('pms.hk.service_status.out_of_service') }}</option></select>
                    <input name="date_from" value="{{ old('date_from', $businessDate->format('d.m.Y')) }}" class="input" aria-label="{{ __('pms.hkscreen.from') }}" placeholder="{{ __('pms.hkscreen.from') }}">
                    <input name="date_to" value="{{ old('date_to', $businessDate->addDay()->format('d.m.Y')) }}" class="input" aria-label="{{ __('pms.hkscreen.to') }}" placeholder="{{ __('pms.hkscreen.to') }}">
                    <select name="reason_code_id" class="input" aria-label="{{ __('pms.audit.override') }}">@foreach ($reasons as $rc)<option value="{{ $rc->id }}">{{ $rc->code }} — {{ $rc->name }}</option>@endforeach</select>
                    <input name="note" value="{{ old('note') }}" class="input" maxlength="500" placeholder="{{ __('pms.hkscreen.note') }}">
                    <button formaction="{{ route('housekeeping.outage') }}" class="btn btn-primary justify-center">{{ __('pms.hkscreen.save_outage') }}</button>
                </fieldset>
            @endif
            <a href="{{ route('housekeeping.mobile') }}" class="btn btn-side mt-auto">{{ __('pms.menu.hk_mobile') }}</a>
            <a href="{{ route('home') }}" class="btn btn-side" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </form>
    @php $hm = $house['movement']; $he = $house['end_of_day']; @endphp
    <div class="flex flex-wrap gap-x-1 gap-y-1 px-2 py-1 border-t border-line bg-brand-50 text-[12px]" data-status-bar>
        @foreach ([['to_sell', $house['total']['to_sell']], ['exp_arr', $hm['expected_arrivals']['rooms']], ['act_arr', $hm['actual_arrivals']['rooms']],
            ['exp_dep', $hm['expected_departures']['rooms']], ['act_dep', $hm['actual_departures']['rooms']], ['room_move', $hm['room_moves']['rooms']],
            ['curr_occ', $hm['current_occupancy']['rooms']], ['curr_occ_pct', number_format($hm['current_occupancy']['pct'], 2, ',', '.')],
            ['avl_ton', $he['available_tonight']], ['inh_ton', $he['in_house_tonight']['rooms']]] as [$key, $value])
            <span class="px-2 py-0.5 rounded border border-line bg-surface">{{ __('pms.hkscreen.bar.'.$key) }}: <b class="tabular-nums" data-bar="{{ $key }}">{{ $value }}</b></span>
        @endforeach
        <a href="{{ route('front-office.house-status') }}" class="px-2 py-0.5 text-brand-600 hover:underline">{{ __('pms.hs.title') }} <span class="kbd">Ctrl+H</span></a>
    </div>
</div>
@endsection
