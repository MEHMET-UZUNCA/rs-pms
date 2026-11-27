@extends('layouts.app')
@section('title', __('pms.res.navigator'))
@section('content')
@php
    $canCancel = Gate::allows('permission', 'reservations.edit');
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
            <a href="{{ route('reservations.navigator', ['f' => $key]) }}" @if ($hotkey) data-hotkey="{{ $hotkey }}" @endif
               @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full border text-[13px] whitespace-nowrap',
                   'bg-brand-600 border-brand-600 text-white' => $filter === $key, 'bg-white border-line hover:bg-brand-50' => $filter !== $key])>
                {{ __('pms.res.filters.'.$key) }} <span class="text-[11px] opacity-70">{{ str_replace(['alt+', 'shift+'], ['Alt+', 'Shift+'], $hotkey) }}</span>
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
                            @foreach ($columns as $col)<th @class(['text-right' => in_array($col, ['nights', 'balance'], true)])>{{ __('pms.res.cols.'.$col) }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr data-id="{{ $r->id }}" data-url-detail="{{ route('reservations.summary', $r) }}" @can('permission', 'reservations.edit') data-url-edit="{{ route('reservations.edit', $r) }}" @endcan data-url-letter="{{ route('reservations.letter', $r) }}"
                                @can('permission', 'cashiering.billing') data-url-billing="{{ route('billing.reservation', $r) }}" @endcan
                                @if ($r->status !== 'in_house' && $r->arrival_date?->isSameDay($businessDate) && $r->room_id) data-url-checkin="{{ route('reservations.check-in', $r) }}" @endif
                                @if ($r->status === 'in_house' && $r->departure_date?->lte($businessDate) && Gate::allows('permission', 'cashiering.billing')) data-url-checkout="{{ route('billing.reservation', $r) }}" @endif
                                @if ($canCancel && in_array($r->status, ['cancelled', 'no_show'], true)) data-url-reinstate="{{ route('reservations.reinstate', $r) }}" @endif
                                @if ($r->status === 'in_house' && $r->check_in_business_date?->isSameDay($businessDate) && Gate::allows('permission', 'reservations.checkin')) data-url-undo="{{ route('reservations.undo-check-in', $r) }}" @endif
                                @if ($canCancel && in_array($r->status, ['inquiry', 'tentative', 'confirmed', 'waitlist'], true)) data-url-cancel="{{ route('reservations.cancel', $r) }}" @endif
                                @if (in_array($r->status, ['confirmed', 'tentative'], true) && $r->arrival_date?->isSameDay($businessDate) && ! $r->queued_at) data-url-queue="{{ route('queue.add', $r) }}" @endif
                                @class(['is-inactive' => in_array($r->status, ['cancelled', 'no_show'], true)])>
                                @foreach ($columns as $col)
                                    @switch($col)
                                        @case('room')<td class="font-semibold">{{ $r->room?->number }}</td>@break
                                        @case('guest')<td>{{ $r->profile?->display_name }}</td>@break
                                        @case('arrival')<td>{{ $r->arrival_date?->format('d.m.Y') }}</td>@break
                                        @case('departure')<td>{{ $r->departure_date?->format('d.m.Y') }}@if ($r->etd && substr((string) $r->etd, 0, 5) > ($coTime ??= \App\Domain\Reservations\Support\ReservationOptions::get($r->property_id)['check_out_time'])) <span class="text-danger font-semibold text-[11px]" title="ETD {{ substr((string) $r->etd, 0, 5) }}">{{ __('pms.card.late_co') }} {{ substr((string) $r->etd, 0, 5) }}</span>@endif</td>@break
                                        @case('nights')<td class="text-right">{{ $r->nights }}</td>@break
                                        @case('room_type')<td>{{ $r->roomType?->code }}</td>@break
                                        @case('rate_code')<td>{{ $r->rateCode?->code }}</td>@break
                                        @case('pax')<td>{{ $r->adults }}@if ($r->children)+{{ $r->children }}@endif</td>@break
                                        @case('company')<td>{{ $r->agent_name ?? $r->company_name }}</td>@break
                                        @case('status')<td><span class="px-2 py-0.5 rounded-full text-[12px] {{ $badge($r->status) }}">{{ __('pms.res.statuses.'.$r->status) }}</span></td>@break
                                        @case('balance')<td class="text-right tabular-nums">{{ $money($r->balance) }}</td>@break
                                        @case('conf_no')<td class="text-muted">{{ $r->confirmation_number }}</td>@break
                                    @endswitch
                                @endforeach
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="{{ count($columns) }}" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="px-3 py-1 border-t border-line text-[12px] text-muted flex gap-4">
                <span>{{ __('pms.common.records', ['count' => $rows->count()]) }} @if ($rows->count() >= $limit){{ __('pms.res.limit_hint', ['limit' => $limit]) }}@endif</span>
                <span class="ml-auto">{{ __('pms.res.keys_hint') }}</span>
            </div>
            <div id="res-detail" @class(['border-t border-line overflow-auto bg-surface shrink-0', \App\Http\Controllers\Reservations\NavigatorController::PANELS[$panel] ?? 'h-48']) @if ($panel === 'none') hidden @endif data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'>
                <p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>
            </div>
        </section>

        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @can('permission', 'reservations.edit')
                @if ($isFa && Gate::allows('permission', 'cashiering.accounts'))
                    <a href="{{ route('accounts.create') }}" class="btn btn-side" data-hotkey="ctrl+n alt+n">{{ __('pms.fa.new') }} <span class="kbd ml-auto">Ctrl+N</span></a>
                @else
    <a href="{{ route('reservations.create') }}" class="btn btn-side" data-hotkey="ctrl+n alt+n">{{ __('pms.common.new') }} <span class="kbd ml-auto">Ctrl+N</span></a>
                @endif
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
            <form method="POST">
                @csrf
                <button class="btn btn-side w-full" data-needs-selection="res-grid" data-action="queue" data-hotkey="alt+q">{{ __('pms.queue.add') }} <span class="kbd ml-auto">Alt+Q</span></button>
            </form>
            @can('permission', 'cashiering.billing')
                <a class="btn btn-side" data-needs-selection="res-grid" data-action="billing" data-hotkey="alt+b">{{ __('pms.res.folio') }} <span class="kbd ml-auto">Alt+B</span></a>
            @endcan
            @can('permission', 'reservations.checkout')
                <a class="btn btn-side" data-needs-selection="res-grid" data-action="checkout" data-hotkey="alt+o">{{ __('pms.billing.check_out') }} <span class="kbd ml-auto">Alt+O</span></a>
            @endcan
            <a class="btn btn-side" data-needs-selection="res-grid" data-action="letter" data-hotkey="alt+l">{{ __('pms.res.letter') }} <span class="kbd ml-auto">Alt+L</span></a>
            @if ($canCancel)
                <form method="POST" data-confirm="{{ __('pms.res.reinstate_confirm') }}">@csrf
                    <button class="btn btn-side w-full" data-needs-selection="res-grid" data-action="reinstate" data-hotkey="alt+i">{{ __('pms.res.reinstate') }} <span class="kbd ml-auto">Alt+I</span></button></form>
            @endif
            @can('permission', 'reservations.checkin')
                <form method="POST" data-confirm="{{ __('pms.res.undo_confirm') }}">@csrf
                    <button class="btn btn-side w-full" data-needs-selection="res-grid" data-action="undo" data-hotkey="alt+u"><span class="truncate" title="{{ __('pms.res.undo_check_in') }}">{{ __('pms.res.undo_check_in_short') }}</span> <span class="kbd ml-auto">Alt+U</span></button></form>
            @endcan
            @if ($canCancel)
                <button type="button" form="cancel-form" class="btn btn-side" data-needs-selection="res-grid" data-action="cancel" data-modal-open="cancel-dialog" data-hotkey="alt+x">{{ __('pms.res.cancel') }} <span class="kbd ml-auto">Alt+X</span></button>
            @endif
            <button type="button" class="btn btn-side mt-auto" data-modal-open="columns-dialog" data-hotkey="alt+k">{{ __('pms.res.columns') }} <span class="kbd ml-auto">Alt+K</span></button>
            <a href="{{ route('home') }}" class="btn btn-side" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
{{-- Kolon ve alt panel tercihleri (kullanıcıya özel) --}}
<div id="columns-dialog" data-modal hidden class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center p-4">
    <form method="POST" action="{{ route('reservations.preferences') }}" class="win w-[26rem] max-w-full" role="dialog" aria-modal="true" aria-labelledby="columns-title">
        @csrf
        <div class="win-title"><span id="columns-title">{{ __('pms.res.columns') }}</span></div>
        <div class="p-3 flex flex-col gap-2">
            <p class="text-[12.5px] text-muted">{{ __('pms.res.columns_hint') }}</p>
            <ul class="border border-line rounded divide-y divide-line/60" data-sortable>
                @foreach (array_merge($columns, array_values(array_diff(\App\Http\Controllers\Reservations\NavigatorController::COLUMNS, $columns))) as $col)
                    <li class="flex items-center gap-2 px-2 py-1">
                        <label class="flex items-center gap-2 flex-1"><input type="checkbox" name="cols[]" value="{{ $col }}" @checked(in_array($col, $columns, true)) @if ($loop->first) data-autofocus @endif> {{ __('pms.res.cols.'.$col) }}</label>
                        <button type="button" class="btn !h-6 !px-2" data-move="up" aria-label="{{ __('pms.res.move_up') }}">↑</button>
                        <button type="button" class="btn !h-6 !px-2" data-move="down" aria-label="{{ __('pms.res.move_down') }}">↓</button>
                    </li>
                @endforeach
            </ul>
            <label><span class="field-label">{{ __('pms.res.panel') }}</span>
                <select name="panel" class="input">@foreach (array_keys(\App\Http\Controllers\Reservations\NavigatorController::PANELS) as $p)<option value="{{ $p }}" @selected($panel === $p)>{{ __('pms.res.panels.'.$p) }}</option>@endforeach</select></label>
        </div>
        <div class="flex gap-2 px-3 py-2 border-t border-line">
            <button name="reset" value="1" class="btn">{{ __('pms.res.columns_reset') }}</button>
            <button class="btn btn-primary ml-auto">{{ __('pms.common.ok') }}</button>
            <button type="button" class="btn" data-modal-close data-hotkey="escape">{{ __('pms.common.cancel') }}</button>
        </div>
    </form>
</div>
@if ($canCancel)
{{-- Rezervasyon iptali: neden zorunlu --}}
<div id="cancel-dialog" data-modal hidden class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center p-4">
    <form method="POST" id="cancel-form" class="win w-[28rem] max-w-full" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
        @csrf
        <div class="win-title"><span id="cancel-title">{{ __('pms.res.cancel_title') }}</span></div>
        <div class="p-3 flex flex-col gap-2">
            <label><span class="field-label">{{ __('pms.res.cancel_reason') }} *</span>
                <select name="reason_code_id" class="input" required data-autofocus><option value=""></option>
                    @foreach ($cancelReasons as $cr)<option value="{{ $cr->id }}">{{ $cr->code }} {{ $cr->name }}</option>@endforeach</select></label>
            <p class="text-[12px] text-muted">{{ __('pms.res.cancel_hint') }}</p>
        </div>
        <div class="flex gap-2 px-3 py-2 border-t border-line">
            <button class="btn btn-primary ml-auto">{{ __('pms.res.cancel') }}</button>
            <button type="button" class="btn" data-modal-close data-hotkey="escape">{{ __('pms.common.close') }}</button>
        </div>
    </form>
</div>
@endif
@endsection
