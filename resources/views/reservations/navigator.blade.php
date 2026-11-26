@extends('layouts.app')
@section('title', __('pms.res.navigator'))
@section('content')
@php
    $badge = fn (string $s) => match ($s) {
        'in_house' => 'bg-ok/15 text-ok', 'confirmed' => 'bg-brand-100 text-brand-700', 'tentative', 'waitlist' => 'bg-amber-100 text-amber-800',
        'cancelled', 'no_show' => 'bg-danger/10 text-danger', default => 'bg-surface text-muted',
    };
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.res.navigator') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.res.business_date_title', ['date' => $businessDate->format('d.m.Y')]) }}</span></div>

    <form method="GET" action="{{ route('reservations.navigator') }}" class="grid gap-2 px-3 py-2 border-b border-line grid-cols-2 md:grid-cols-4 xl:grid-cols-8 items-end" data-navigator-query>
        <label><span class="field-label">{{ __('pms.res.name') }} <span class="kbd">Alt+A</span></span>
            <input name="ad" value="{{ $query['ad'] }}" class="input" autocomplete="off" data-hotkey="alt+a" @if (! $filter) autofocus @endif></label>
        <label><span class="field-label">{{ __('pms.res.room') }}</span><input name="oda" value="{{ $query['oda'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">{{ __('pms.res.conf_no') }}</span><input name="no" value="{{ $query['no'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">{{ __('pms.res.company_agent') }}</span><input name="firma" value="{{ $query['firma'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">{{ __('pms.res.arrival') }} <span class="text-muted">{{ __('pms.res.date_hint') }}</span></span><input name="gelis" value="{{ $query['gelis'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">{{ __('pms.res.departure') }}</span><input name="cikis" value="{{ $query['cikis'] }}" class="input" autocomplete="off"></label>
        <div class="flex gap-2 md:col-span-2 xl:col-span-2">
            <select name="durum" class="input min-w-36" aria-label="{{ __('pms.res.status') }}">
                <option value="">{{ __('pms.res.all_statuses') }}</option>
                @foreach (array_keys(\App\Http\Controllers\Reservations\NavigatorController::STATUSES) as $value)
                    <option value="{{ $value }}" @selected($query['durum'] === $value)>{{ __('pms.res.statuses.'.$value) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" data-hotkey="f3" data-search-button>{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
        </div>
    </form>

    <nav class="flex flex-wrap gap-1.5 px-3 py-2 border-b border-line" aria-label="Filters">
        @foreach (\App\Http\Controllers\Reservations\NavigatorController::FILTERS as $key => [, $hotkey])
            <a href="{{ route('reservations.navigator', ['f' => $key]) }}" data-hotkey="{{ $hotkey }}"
               @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full border text-[13px] whitespace-nowrap',
                   'bg-brand-600 border-brand-600 text-white' => $filter === $key, 'bg-white border-line hover:bg-brand-50' => $filter !== $key])>
                {{ __('pms.res.filters.'.$key) }} <span class="text-[11px] opacity-70">{{ ucfirst(str_replace('alt+', 'Alt+', $hotkey)) }}</span>
            </a>
        @endforeach
    </nav>

    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-auto">
                @if ($isFa)
                    <table class="grid-table" id="res-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#res-detail">
                        <thead><tr><th>{{ __('pms.res.fa_number') }}</th><th>{{ __('pms.common.name') }}</th><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.res.validity') }}</th><th>{{ __('pms.res.status') }}</th><th class="text-right">{{ __('pms.common.balance') }}</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $folio)
                            <tr data-id="{{ $folio->id }}" data-url-detail="{{ route('reservations.folio-summary', $folio) }}"
                                @can('permission', 'cashiering.billing') data-url-billing="{{ route('billing.account', $folio) }}" data-url-edit="{{ route('billing.account', $folio) }}" @endcan>
                                <td class="font-semibold">{{ $folio->folio_number }}</td><td>{{ $folio->name }}</td><td>{{ $folio->type }}</td>
                                <td>{{ $folio->valid_from?->format('d.m.Y') }} – {{ $folio->valid_until?->format('d.m.Y') }}</td>
                                <td>{{ $folio->status === 'open' ? __('pms.res.open') : __('pms.res.closed') }}</td>
                                <td class="text-right tabular-nums">{{ $money($folio->balance) }}</td>
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="6" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                        </tbody>
                    </table>
                @else
                    <table class="grid-table" id="res-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#res-detail">
                        <thead><tr>
                            <th>{{ __('pms.res.room') }}</th><th>{{ __('pms.res.guest') }}</th><th>{{ __('pms.res.arrival') }}</th><th>{{ __('pms.res.departure') }}</th><th class="text-right">{{ __('pms.res.nights') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.res.rate_code') }}</th>
                            <th>{{ __('pms.res.pax') }}</th><th>{{ __('pms.res.company_agent') }}</th><th>{{ __('pms.res.status') }}</th><th class="text-right">{{ __('pms.common.balance') }}</th><th>{{ __('pms.res.conf_no') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr data-id="{{ $r->id }}" data-url-detail="{{ route('reservations.summary', $r) }}" @can('permission', 'reservations.edit') data-url-edit="{{ route('reservations.edit', $r) }}" @endcan data-url-letter="{{ route('reservations.letter', $r) }}"
                                @can('permission', 'cashiering.billing') data-url-billing="{{ route('billing.reservation', $r) }}" @endcan
                                @if ($r->status !== 'in_house' && $r->arrival_date?->isSameDay($businessDate) && $r->room_id) data-url-checkin="{{ route('reservations.check-in', $r) }}" @endif
                                @class(['is-inactive' => in_array($r->status, ['cancelled', 'no_show'], true)])>
                                <td class="font-semibold">{{ $r->room?->number }}</td>
                                <td>{{ $r->profile?->display_name }}</td>
                                <td>{{ $r->arrival_date?->format('d.m.Y') }}</td><td>{{ $r->departure_date?->format('d.m.Y') }}</td>
                                <td class="text-right">{{ $r->nights }}</td>
                                <td>{{ $r->roomType?->code }}</td><td>{{ $r->rateCode?->code }}</td>
                                <td>{{ $r->adults }}@if ($r->children)+{{ $r->children }}@endif</td>
                                <td>{{ $r->agent_name ?? $r->company_name }}</td>
                                <td><span class="px-2 py-0.5 rounded-full text-[12px] {{ $badge($r->status) }}">{{ __('pms.res.statuses.'.$r->status) }}</span></td>
                                <td class="text-right tabular-nums">{{ $money($r->balance) }}</td>
                                <td class="text-muted">{{ $r->confirmation_number }}</td>
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="12" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="px-3 py-1 border-t border-line text-[12px] text-muted flex gap-4">
                <span>{{ __('pms.common.records', ['count' => $rows->count()]) }} @if ($rows->count() >= $limit){{ __('pms.res.limit_hint', ['limit' => $limit]) }}@endif</span>
                <span class="ml-auto">{{ __('pms.res.keys_hint') }}</span>
            </div>
            <div id="res-detail" class="border-t border-line min-h-44 max-h-[40%] overflow-auto bg-surface" data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'>
                <p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @can('permission', 'reservations.edit')
                <a href="{{ route('reservations.create') }}" class="btn btn-side" data-hotkey="ctrl+n alt+n">{{ __('pms.common.new') }} <span class="kbd ml-auto">Ctrl+N</span></a>
            @endcan
            @can('permission', 'reservations.edit')
                <a class="btn btn-side" data-needs-selection="res-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Enter</span></a>
            @endcan
            @can('permission', 'reservations.checkin')
                <form method="POST" data-confirm="{{ __('pms.res.check_in_confirm') }}">
                    @csrf
                    <button class="btn btn-side" data-needs-selection="res-grid" data-action="checkin" data-hotkey="alt+g">{{ __('pms.res.check_in') }} <span class="kbd ml-auto">Alt+G</span></button>
                </form>
            @endcan
            @can('permission', 'cashiering.billing')
                <a class="btn btn-side" data-needs-selection="res-grid" data-action="billing" data-hotkey="alt+b">{{ __('pms.res.folio') }} <span class="kbd ml-auto">Alt+B</span></a>
            @endcan
            <a class="btn btn-side" data-needs-selection="res-grid" data-action="letter" data-hotkey="alt+l">{{ __('pms.res.letter') }} <span class="kbd ml-auto">Alt+L</span></a>
            <span class="btn btn-side opacity-40" title="{{ __('pms.menu.coming_soon') }}">{{ __('pms.res.cancel') }}</span>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
