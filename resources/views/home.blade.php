@extends('layouts.app')
@section('title', __('pms.common.home'))
@section('content')
@php
    $money = fn ($x) => number_format((float) $x, 2, ',', '.');
    $canRes = Gate::allows('permission', 'reservations.view');
    $canEdit = Gate::allows('permission', 'reservations.edit');
    $canCheckIn = Gate::allows('permission', 'reservations.checkin');
    $canBill = Gate::allows('permission', 'cashiering.billing');
    $canCheckOut = $canBill && Gate::allows('permission', 'reservations.checkout');
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ $dash ? __('pms.dash.title') : __('pms.common.home') }}</span>
        @if ($dash)<span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.business_date') }} {{ $dash['business_date']->format('d.m.Y l') }}</span>@endif</div>
    @if ($dash)
        @php
            $s = $dash['status']; $m = $s['movement']; $hk = $s['housekeeping']; $eod = $s['end_of_day']; $lc = $dash['last_closed'];
            $tiles = [
                ['arrivals', $m['actual_arrivals']['rooms'].' / '.($m['actual_arrivals']['rooms'] + $m['expected_arrivals']['rooms']), __('pms.dash.pax', ['n' => $m['expected_arrivals']['pax']]).' '.__('pms.dash.still_expected'), $canRes ? route('reservations.navigator', ['f' => 'gelecek']) : null],
                ['departures', $m['actual_departures']['rooms'].' / '.($m['actual_departures']['rooms'] + $m['expected_departures']['rooms']), __('pms.dash.rooms_left', ['n' => $m['expected_departures']['rooms']]), $canRes ? route('reservations.navigator', ['f' => 'gidecek']) : null],
                ['in_house', $m['current_occupancy']['rooms'], __('pms.dash.pax', ['n' => $m['current_occupancy']['pax']]), $canRes ? route('reservations.navigator', ['f' => 'iceride']) : null],
                ['occupancy', '%'.number_format($s['total']['to_sell'] > 0 ? $eod['in_house_tonight']['rooms'] / $s['total']['to_sell'] * 100 : 0, 1, ',', '.'), __('pms.dash.tonight_rooms', ['n' => $eod['in_house_tonight']['rooms'], 'total' => $s['total']['to_sell']]), route('front-office.house-status')],
                ['adr', $money($eod['adr_gross']), __('pms.dash.net', ['amount' => $money($eod['adr_net'])]), route('front-office.house-status')],
                ['available', $eod['available_tonight'], __('pms.dash.ooo', ['n' => $hk['out_of_order']['total']]), Route::has('front-office.room-rack') && $canRes ? route('front-office.room-rack') : null],
                ['dirty', $hk['dirty']['vacant'], __('pms.dash.dirty_occupied', ['n' => $hk['dirty']['occupied']]), Gate::allows('permission', 'housekeeping.view') ? route('housekeeping.index') : null],
                ['queue', $hk['queue'], __('pms.dash.queue_hint'), $canRes ? route('queue.index') : null],
            ];
        @endphp
        <div class="flex-1 min-h-0 overflow-auto p-3 flex flex-col gap-3">
            <section class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2" aria-label="{{ __('pms.dash.today') }}">
                @foreach ($tiles as [$key, $value, $sub, $url])
                    <a @if ($url) href="{{ $url }}" @endif data-tile="{{ $key }}" @class(['win !min-h-fit p-3 flex flex-col gap-0.5', 'hover:border-brand-400' => $url, 'pointer-events-none' => ! $url])>
                        <span class="text-[12px] text-muted">{{ __('pms.dash.tiles.'.$key) }}</span>
                        <span class="text-[22px] font-semibold tabular-nums leading-tight" data-value>{{ $value }}</span>
                        <span class="text-[11px] text-muted">{{ $sub }}</span>
                    </a>
                @endforeach
            </section>

            <div class="grid gap-3 xl:grid-cols-[3fr_2fr]">
                <section class="win !min-h-fit p-3 min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-1 mb-2"><b>{{ __('pms.dash.forecast', ['days' => count($dash['forecast'])]) }}</b>
                        <span class="text-[11px] text-muted flex gap-3"><span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-brand-600 align-middle"></i> {{ __('pms.dash.sold') }}</span>
                            <span><i class="inline-block w-2.5 h-2.5 rounded-sm bg-brand-200 align-middle"></i> {{ __('pms.dash.tentative') }}</span></span></div>
                    <div class="overflow-x-auto"><table class="grid-table min-w-[30rem]" data-forecast>
                        <thead><tr><th>{{ __('pms.common.date') }}</th><th class="text-right">{{ __('pms.dash.sold') }}</th><th class="text-right">{{ __('pms.dash.tentative') }}</th>
                            <th class="text-right">OOO</th><th class="text-right">{{ __('pms.dash.free') }}</th><th class="w-1/2">%</th></tr></thead>
                        <tbody>
                        @foreach ($dash['forecast'] as $day)
                            @php $w = fn ($n) => $day['sellable'] > 0 ? min(100, $n / $day['sellable'] * 100) : 0; @endphp
                            <tr @class(['font-semibold' => $loop->first, 'bg-panel' => $day['date']->isWeekend()])>
                                <td class="whitespace-nowrap">{{ $day['date']->format('d.m D') }}</td>
                                <td class="text-right tabular-nums">{{ $day['sold'] }}</td><td class="text-right tabular-nums">{{ $day['tentative'] ?: '' }}</td>
                                <td class="text-right tabular-nums">{{ $day['ooo'] ?: '' }}</td><td class="text-right tabular-nums">{{ max(0, $day['sellable'] - $day['sold'] - $day['tentative']) }}</td>
                                <td><div class="flex items-center gap-2"><div class="flex-1 h-3 rounded-sm bg-line/60 overflow-hidden flex" role="img" aria-label="%{{ $day['pct'] }}">
                                    <div class="h-full bg-brand-600" style="width: {{ $w($day['sold']) }}%"></div><div class="h-full bg-brand-200" style="width: {{ $w($day['tentative']) }}%"></div></div>
                                    <span class="w-12 text-right tabular-nums text-[12px]">%{{ number_format($day['pct'], 1, ',', '.') }}</span></div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </section>

                <section class="win !min-h-fit p-3 flex flex-col gap-2 min-w-0">
                    <b>{{ __('pms.dash.last_closed') }}@if ($lc) <span class="font-normal text-muted">{{ \Illuminate\Support\Carbon::parse($lc->business_date)->format('d.m.Y') }}</span>@endif</b>
                    @if ($lc)
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-[13px]">
                            <dt class="text-muted">{{ __('pms.dash.occupancy_pct') }}</dt><dd class="text-right tabular-nums">%{{ number_format((float) $lc->occupancy_pct, 2, ',', '.') }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.rooms_sold') }}</dt><dd class="text-right tabular-nums">{{ $lc->rooms_sold }} / {{ $lc->rooms_available }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.room_revenue') }}</dt><dd class="text-right tabular-nums font-semibold">{{ $money($lc->room_revenue) }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.fnb_revenue') }}</dt><dd class="text-right tabular-nums">{{ $money((float) $lc->breakfast_revenue + (float) $lc->lunch_revenue + (float) $lc->dinner_revenue + (float) $lc->fnb_revenue) }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.other_revenue') }}</dt><dd class="text-right tabular-nums">{{ $money($lc->other_revenue) }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.total_revenue') }}</dt><dd class="text-right tabular-nums font-semibold">{{ $money($lc->total_revenue) }}</dd>
                            <dt class="text-muted">ADR / RevPAR</dt><dd class="text-right tabular-nums">{{ $money($lc->adr) }} / {{ $money($lc->revpar) }}</dd>
                            <dt class="text-muted">{{ __('pms.dash.movements') }}</dt><dd class="text-right tabular-nums">{{ $lc->arrivals }} ↓ · {{ $lc->departures }} ↑ · {{ $lc->no_shows }} NS</dd>
                        </dl>
                        <p class="text-[11px] text-muted">{{ __('pms.dash.net_hint') }}</p>
                    @else
                        <p class="text-muted">{{ __('pms.dash.no_audit') }}</p>
                    @endif
                </section>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                @foreach (['arrivals' => 'eta', 'departures' => 'etd'] as $list => $time)
                    <section class="win !min-h-fit p-3 min-w-0">
                        <b>{{ __('pms.dash.list_'.$list) }} <span class="font-normal text-muted">({{ $dash[$list]->count() }}{{ $dash[$list]->count() >= 12 ? '+' : '' }})</span></b>
                        <div class="overflow-x-auto"><table class="grid-table mt-1 min-w-[26rem]" data-list="{{ $list }}">
                            <thead><tr><th>{{ __('pms.prof.name') }}</th><th>{{ __('pms.card.room') }}</th><th>{{ $list === 'arrivals' ? 'ETA' : 'ETD' }}</th><th class="text-right">{{ __('pms.dash.pax_short') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($dash[$list] as $row)
                                @php
                                    $bdKey = $dash['business_date']->toDateString();
                                    $actions = array_filter([
                                        'card' => $canEdit ? route('reservations.edit', $row->id) : null,
                                        'room' => $canEdit && $list === 'arrivals' && ! $row->room ? route('reservations.edit', $row->id) : null,
                                        'checkin' => $canCheckIn && $list === 'arrivals' && $row->room ? route('reservations.check-in', $row->id) : null,
                                        'folio' => $canBill && $list === 'departures' ? route('billing.reservation', $row->id) : null,
                                        'checkout' => $canCheckOut && $list === 'departures' ? route('billing.reservation', $row->id) : null,
                                        'letter' => $canRes && $list === 'arrivals' ? route('reservations.letter', $row->id) : null,
                                        'navigator' => $canRes ? route('reservations.navigator', ['secili' => $row->id, 'no' => $row->confirmation_number]) : null,
                                    ]);
                                @endphp
                                <tr @if ($actions) data-row-actions='@json($actions)' tabindex="0" class="cursor-pointer focus:outline-none focus:bg-row-selected" @endif>
                                    <td class="font-semibold">@if ($canRes)<a class="hover:underline" href="{{ route('reservations.edit', $row->id) }}">{{ $row->display_name }}</a>@else{{ $row->display_name }}@endif
                                        @if ($row->vip) <span class="text-[11px] px-1 rounded bg-warning/20">VIP {{ $row->vip }}</span>@endif
                                        @if ($row->profile_status === 'blacklisted') <span class="text-[11px] text-danger">{{ __('pms.prof.statuses.blacklisted') }}</span>@endif</td>
                                    <td>{{ $row->room ?? '—' }} <span class="text-muted text-[11px]">{{ $row->room_type }}</span>
                                        @if ($list === 'arrivals' && $row->room && in_array($row->hk_status, ['dirty', 'pickup'], true)) <span class="text-[11px] text-danger">{{ __('pms.hk.hk_status.'.$row->hk_status) }}</span>@endif</td>
                                    <td class="tabular-nums">{{ $row->{$time} ? substr((string) $row->{$time}, 0, 5) : '' }}</td>
                                    <td class="text-right tabular-nums">{{ $row->adults }}@if ($row->children)+{{ $row->children }}@endif</td>
                                    <td class="text-[11px]">@if ($list === 'arrivals' && $row->queued_at)<span class="text-brand-700">{{ __('pms.dash.queued') }}</span>@endif
                                        @if ($list === 'departures' && \Illuminate\Support\Carbon::parse($row->departure_date)->lt($dash['business_date']))<span class="text-danger">{{ __('pms.dash.overdue') }}</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">{{ __('pms.common.no_records') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </section>
                @endforeach
            </div>
        </div>
        @include('partials.row-menu')
    @else
        <div class="p-4 text-muted">
            <p>{{ __('pms.common.available_screens') }}</p>
            <ul class="list-disc ml-5 mt-2 text-ink" data-available-screens>
                @foreach (\App\Support\Menu::items() as $menu => $items)
                    @foreach ($items as $item)
                        @if ($item[1] && $menu !== 'quick_keys')<li><a class="text-brand-600 hover:underline" href="{{ $item[1] }}">{{ __('pms.menu.'.$menu) }} → {{ __('pms.menu.'.$item[0]) }}</a></li>@endif
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
