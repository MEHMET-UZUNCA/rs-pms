@extends('layouts.app')
@php
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
    $title = $reservation ? __('pms.billing.title').' — '.$reservation->profile?->display_name : __('pms.billing.fa_title', ['name' => $folio?->folio_number.' '.$folio?->name]);
    $target = $reservation ? ['reservation_id' => $reservation->id, 'window' => $current] : ['folio_id' => $folio?->id];
    $closed = $folio && $folio->status === 'closed';
    $canPost = Gate::allows('permission', 'cashiering.post') && ! $closed && (! $reservation || in_array($reservation->status, ['in_house', 'confirmed', 'tentative'], true));
    $url = fn (int $n) => $reservation ? route('billing.reservation', [$reservation, 'pencere' => $n] + ($showReversed ? ['duzeltmeler' => 1] : [])) : '#';
@endphp
@section('title', $title)
@section('content')
<div class="win flex-1">
    <div class="win-title">
        <span>{{ $title }}</span>
        <span class="font-normal text-brand-100 text-[12px]">{{ __('pms.billing.total_balance') }}: {{ $money($windows->sum('balance')) }}</span>
    </div>

    @if ($reservation)
        <div class="flex flex-wrap gap-x-6 gap-y-1 px-3 py-2 border-b border-line text-[13px]">
            <span><span class="text-muted">{{ __('pms.billing.room') }}:</span> <b>{{ $reservation->room?->number ?? '—' }}</b> {{ $reservation->roomType?->code }}</span>
            <span><span class="text-muted">{{ __('pms.billing.stay') }}:</span> {{ $reservation->arrival_date->format('d.m.Y') }} – {{ $reservation->departure_date->format('d.m.Y') }}</span>
            <span><span class="text-muted">{{ __('pms.billing.rate') }}:</span> {{ $reservation->rateCode?->code }}</span>
            <span><span class="text-muted">{{ __('pms.res.status') }}:</span> {{ __('pms.res.statuses.'.$reservation->status) }}</span>
            <span><span class="text-muted">{{ __('pms.res.conf_no') }}:</span> {{ $reservation->confirmation_number }}</span>
        </div>
        <nav class="flex flex-wrap gap-0.5 px-2 pt-2 border-b border-line" aria-label="Windows">
            @foreach (range(1, 8) as $n)
                @php $w = $windows->get($n); @endphp
                <a href="{{ $url($n) }}" data-hotkey="alt+{{ $n }}" @class(['px-3 h-8 -mb-px inline-flex items-center gap-2 border rounded-t text-[13px] whitespace-nowrap',
                    'bg-panel border-line border-b-panel font-semibold' => $n === $current, 'border-transparent hover:bg-brand-50' => $n !== $current, 'text-muted' => ! $w])>
                    {{ __('pms.billing.window', ['n' => $n]) }} @if ($w)<span class="tabular-nums">{{ $money($w->balance) }}</span>@endif <span class="text-[11px] text-muted">Alt+{{ $n }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="post-grid" data-grid>
                    <thead><tr>
                        <th>{{ __('pms.billing.date') }}</th><th>{{ __('pms.billing.code') }}</th><th>{{ __('pms.billing.description') }}</th>
                        <th class="text-right">{{ __('pms.billing.qty') }}</th><th class="text-right">{{ __('pms.billing.amount') }}</th>
                        <th class="text-right">{{ __('pms.billing.tax') }}</th><th>{{ __('pms.billing.reference') }}</th><th>{{ __('pms.billing.user') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($postings as $p)
                        @php $active = ! $p->reversed_by_id && ! $p->reversal_of_id; @endphp
                        <tr data-id="{{ $p->id }}" @class(['is-inactive' => ! $active])
                            @if ($active && Gate::allows('permission', 'cashiering.reverse')) data-url-correct="{{ route('billing.correct', $p->id) }}" @endif
                            @if ($active && $reservation && Gate::allows('permission', 'cashiering.post')) data-url-transfer="{{ route('billing.transfer', $p->id) }}" @endif>
                            <td>{{ \Illuminate\Support\Carbon::parse($p->business_date)->format('d.m.Y') }}</td>
                            <td class="font-semibold">{{ $p->code }}</td>
                            <td>{{ $p->description }} @if ($p->reversed_by_id)<span class="text-[11px]">({{ __('pms.billing.reversed') }})</span>@endif</td>
                            <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $p->quantity, 2, ',', ''), '0'), ',') }}</td>
                            <td class="text-right tabular-nums">{{ $money($p->amount) }}</td>
                            <td class="text-right tabular-nums text-muted">{{ (float) $p->tax_amount ? $money($p->tax_amount) : '' }}</td>
                            <td class="text-muted">{{ $p->reference }}</td>
                            <td class="text-muted">{{ $p->username ?? $p->source }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($postings->isNotEmpty()) hidden @endif><td colspan="8" class="text-center text-muted py-6">{{ __('pms.billing.empty_window') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center gap-4 px-3 py-1.5 border-t border-line text-[13px]">
                <label class="flex items-center gap-1.5 text-[12px] text-muted"><input type="checkbox" @checked($showReversed)
                    onchange="location.href = '{{ $reservation ? route('billing.reservation', [$reservation, 'pencere' => $current]) : ($folio ? route('billing.account', $folio) : '#') }}' + (this.checked ? '{{ $reservation ? '&' : '?' }}duzeltmeler=1' : '')"> {{ __('pms.billing.show_reversed') }}</label>
                <span class="ml-auto">{{ __('pms.billing.balance') }}: <b class="tabular-nums">{{ $money($folio?->balance ?? 0) }}</b>@if ($closed) · {{ __('pms.billing.closed') }}@endif</span>
            </div>

            @if ($canPost)
                <div class="grid gap-2 px-3 py-2 border-t border-line bg-surface lg:grid-cols-2">
                    <form method="POST" action="{{ route('billing.post') }}" class="flex flex-wrap items-end gap-2">
                        @csrf @foreach ($target as $k => $val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endforeach
                        <b class="w-full text-[12px]">{{ __('pms.billing.post') }} <span class="kbd">Alt+P</span></b>
                        <label class="w-24"><span class="field-label">{{ __('pms.billing.department_code') }}</span>
                            <input name="code" list="manual-codes" value="{{ old('code') }}" class="input @error('code') input-error @enderror" data-hotkey="alt+p" autocomplete="off" required></label>
                        <datalist id="manual-codes">@foreach ($manualCodes as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach</datalist>
                        <label class="w-28"><span class="field-label">{{ __('pms.billing.unit_price') }}</span><input name="amount" value="{{ old('amount') }}" inputmode="decimal" class="input" required></label>
                        <label class="w-16"><span class="field-label">{{ __('pms.billing.qty') }}</span><input name="quantity" value="{{ old('quantity', 1) }}" inputmode="decimal" class="input"></label>
                        <label class="flex-1 min-w-32"><span class="field-label">{{ __('pms.billing.supplement') }}</span><input name="description" value="{{ old('description') }}" class="input" maxlength="100"></label>
                        <button class="btn btn-primary">{{ __('pms.billing.post_button') }}</button>
                    </form>
                    <form method="POST" action="{{ route('billing.payment') }}" class="flex flex-wrap items-end gap-2">
                        @csrf @foreach ($target as $k => $val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endforeach
                        <b class="w-full text-[12px]">{{ __('pms.billing.payment') }} <span class="kbd">Alt+Y</span></b>
                        <label class="flex-1 min-w-40"><span class="field-label">{{ __('pms.billing.payment_code') }}</span>
                            <select name="payment_code_id" class="input" data-hotkey="alt+y" required>
                                @foreach ($paymentCodes as $c)<option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>@endforeach
                            </select></label>
                        <label class="w-28"><span class="field-label">{{ __('pms.billing.amount') }}</span>
                            <input name="amount" value="{{ number_format(max(0, (float) ($folio?->balance ?? 0)), 2, '.', '') }}" inputmode="decimal" class="input" required></label>
                        <button class="btn btn-primary">{{ __('pms.billing.pay_button') }}</button>
                    </form>
                </div>
            @endif
        </section>

        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @can('permission', 'cashiering.reverse')
                <form method="POST" data-confirm="{{ __('pms.billing.correction_confirm') }}">
                    @csrf
                    <button class="btn btn-side" data-needs-selection="post-grid" data-action="correct" data-hotkey="alt+r">{{ __('pms.billing.correction') }} <span class="kbd ml-auto">Alt+R</span></button>
                </form>
            @endcan
            @if ($reservation && $canPost)
                <form method="POST" class="flex flex-col gap-1">
                    @csrf
                    <button class="btn btn-side" data-needs-selection="post-grid" data-action="transfer" data-hotkey="alt+t">{{ __('pms.billing.transfer') }} <span class="kbd ml-auto">Alt+T</span></button>
                    <select name="window" class="input" aria-label="{{ __('pms.billing.to_window') }}">
                        @foreach (range(1, 8) as $n)@if ($n !== $current)<option value="{{ $n }}">→ {{ __('pms.billing.window', ['n' => $n]) }}</option>@endif @endforeach
                    </select>
                </form>
            @endif
            @if ($reservation && $reservation->status === 'in_house' && Gate::allows('permission', 'reservations.checkout'))
                <form method="POST" action="{{ route('billing.check-out', $reservation) }}" data-confirm="{{ __('pms.billing.check_out_confirm') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-side" data-hotkey="alt+o">{{ __('pms.billing.check_out') }} <span class="kbd ml-auto">Alt+O</span></button>
                </form>
            @endif
            <a href="{{ route('reservations.navigator', $reservation ? ['secili' => $reservation->id] : ['f' => 'fa']) }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
