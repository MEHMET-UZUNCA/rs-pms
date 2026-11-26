@extends('layouts.app')
@section('title', __('pms.audit.title'))
@php
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
    $blocking = collect($checks)->filter(fn ($c) => $c['severity'] === 'block' && $c['items']->isNotEmpty());
    $canOverride = Gate::allows('permission', 'night_audit.override');
    $stepIcon = ['done' => '✓', 'running' => '…', 'failed' => '✕', 'pending' => '○'];
@endphp
@section('content')
<form method="POST" action="{{ route('night-audit.run') }}" class="win flex-1" data-confirm="{{ __('pms.audit.run_confirm', ['date' => $businessDate->format('d.m.Y')]) }}">
    @csrf
    <div class="win-title"><span>{{ __('pms.audit.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.audit.closing', ['date' => $businessDate->format('d.m.Y')]) }}</span></div>

    <div class="flex-1 min-h-0 overflow-auto p-3 flex flex-col gap-3">
      <div class="grid gap-3 xl:grid-cols-[2fr_1fr] items-start shrink-0">
        <section class="win !min-h-fit p-3 flex flex-col gap-3">
            <b>{{ __('pms.audit.checks') }}</b>
            @foreach ($checks as $code => $check)
                @php $count = $check['items']->count(); $block = $check['severity'] === 'block' && $count > 0; @endphp
                <div @class(['rounded border p-2.5', 'border-danger/40 bg-danger/5' => $block, 'border-amber-300 bg-amber-50' => ! $block && $count, 'border-line' => ! $count]) data-check="{{ $code }}">
                    <div class="flex items-center gap-2">
                        <span @class(['text-[11px] px-1.5 rounded', 'bg-danger text-white' => $check['severity'] === 'block', 'bg-amber-500 text-white' => $check['severity'] === 'warn'])>{{ __('pms.audit.severity.'.$check['severity']) }}</span>
                        <b>{{ __('pms.audit.check.'.$code) }}</b>
                        <span class="ml-auto text-muted">{{ $count ? __('pms.audit.items', ['count' => $count]) : __('pms.audit.none') }}</span>
                    </div>
                    @if ($count && in_array($code, ['due_out_accounts', 'expected_accounts'], true))
                        <table class="grid-table mt-2"><tbody>
                            @foreach ($check['items'] as $fa)
                                <tr><td class="w-24 font-semibold">{{ $fa->folio_number }}</td><td>{{ $fa->name }}</td>
                                    <td class="text-muted">{{ $fa->valid_from?->format('d.m') }}–{{ $fa->valid_until?->format('d.m') }}</td>
                                    <td class="text-right">@can('permission', 'cashiering.billing')<a class="text-brand-600 hover:underline" href="{{ route('billing.account', $fa) }}">{{ __('pms.res.folio') }}</a>@endcan</td></tr>
                            @endforeach
                        </tbody></table>
                    @elseif ($count && $code !== 'open_cashier_shifts')
                        <table class="grid-table mt-2"><tbody>
                            @foreach ($check['items'] as $r)
                                <tr><td class="w-14 font-semibold">{{ $r->room?->number }}</td><td>{{ $r->profile?->display_name }}</td>
                                    <td class="text-muted">{{ $r->arrival_date->format('d.m') }}–{{ $r->departure_date->format('d.m') }}</td>
                                    <td class="text-muted">{{ $r->confirmation_number }}</td>
                                    <td class="text-right">@can('permission', 'cashiering.billing')<a class="text-brand-600 hover:underline" href="{{ route('billing.reservation', $r) }}">{{ __('pms.res.folio') }}</a>@endcan</td></tr>
                            @endforeach
                        </tbody></table>
                    @endif
                    @if ($block && $canOverride)
                        <label class="block mt-2"><span class="field-label">{{ __('pms.audit.override') }}</span>
                            <input name="override[{{ $code }}]" class="input" maxlength="200" placeholder="{{ __('pms.audit.override_hint') }}"></label>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="win !min-h-fit p-3 flex flex-col gap-2">
            <b>{{ __('pms.audit.steps') }}</b>
            <ol class="flex flex-col gap-1.5">
                @foreach (\App\Domain\NightAudit\Services\NightAuditService::STEPS as $i => $step)
                    @php $s = $steps->get($step); $status = $s->status ?? 'pending'; @endphp
                    <li class="flex items-center gap-2" data-step="{{ $step }}" data-status="{{ $status }}">
                        <span @class(['w-6 h-6 rounded-full grid place-items-center text-[12px] font-semibold', 'bg-ok text-white' => $status === 'done',
                            'bg-danger text-white' => $status === 'failed', 'bg-brand-500 text-white' => $status === 'running', 'bg-surface border border-line text-muted' => $status === 'pending'])>{{ $status === 'pending' ? $i + 1 : $stepIcon[$status] }}</span>
                        <span>{{ __('pms.audit.step.'.$step) }}</span>
                        <span class="ml-auto text-[12px] text-muted">{{ __('pms.audit.step_status.'.$status) }}</span>
                    </li>
                    @if ($s?->error)<li class="text-[12px] text-danger ml-8">{{ $step === 'checks' ? collect(explode(', ', $s->error))->map(fn ($c) => \Illuminate\Support\Facades\Lang::has('pms.audit.check.'.$c) ? __('pms.audit.check.'.$c) : $c)->implode(', ') : $s->error }}</li>@endif
                @endforeach
            </ol>
            @can('permission', 'night_audit.run')
                <button class="btn btn-primary justify-center mt-2" data-hotkey="f10" @disabled($blocking->isNotEmpty() && ! $canOverride)>
                    {{ $current && $current->status === 'held' ? __('pms.audit.continue') : __('pms.audit.run') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span>
                </button>
            @endcan

            <div class="border-t border-line mt-2 pt-2 flex flex-col gap-1.5" data-answers>
                <b>{{ __('pms.audit.answers') }}</b>
                @foreach ($answers as $code => $answer)
                    <label class="flex items-center gap-2 text-[12px]">
                        <span class="flex-1">{{ __('pms.audit.check.'.$code) }}</span>
                        @if (in_array($code, \App\Domain\NightAudit\Support\NightAuditAnswers::FIXED, true) || ! $canOverride)
                            <span class="shrink-0 text-right font-semibold" style="width: 9rem" title="{{ in_array($code, \App\Domain\NightAudit\Support\NightAuditAnswers::FIXED, true) ? __('pms.audit.answer_fixed') : '' }}">{{ __('pms.audit.answer.'.$answer) }}</span>
                        @else
                            <select name="answer[{{ $code }}]" form="na-answers" class="input shrink-0" style="width: 9rem">
                                @foreach (\App\Domain\NightAudit\Support\NightAuditAnswers::ANSWERS as $option)
                                    <option value="{{ $option }}" @selected($option === $answer)>{{ __('pms.audit.answer.'.$option) }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
                @if ($canOverride)
                    <button class="btn justify-center" form="na-answers">{{ __('pms.audit.save_answers') }}</button>
                @endif
            </div>
        </section>
      </div>

        @if ($last && $stats)
            <section class="win !min-h-fit p-3 shrink-0">
                <b>{{ __('pms.audit.last', ['date' => \Illuminate\Support\Carbon::parse($last->business_date)->format('d.m.Y')]) }}</b>
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 mt-2">
                    @foreach ([['occupancy', number_format((float) $stats->occupancy_pct, 1, ',', '.').' %'], ['rooms_sold', $stats->rooms_sold.' / '.$stats->rooms_available],
                        ['adr', $money($stats->adr)], ['revpar', $money($stats->revpar)], ['room_revenue', $money($stats->room_revenue)], ['total_revenue', $money($stats->total_revenue)],
                        ['arrivals', $stats->arrivals.' / '.$stats->departures], ['no_shows', $stats->no_shows]] as [$label, $value])
                        <div class="rounded border border-line p-2"><div class="text-[12px] text-muted">{{ __('pms.audit.'.$label) }}@if ($label === 'arrivals') / {{ __('pms.audit.departures') }}@endif</div><div class="text-[16px] font-semibold tabular-nums" data-stat="{{ $label }}">{{ $value }}</div></div>
                    @endforeach
                </div>
                @if ($revenue->isNotEmpty())
                    <div class="mt-3 text-[12px] text-muted">{{ __('pms.audit.revenue_by_code') }}</div>
                    <table class="grid-table mt-1">
                        <thead><tr><th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.description') }}</th><th class="text-right">{{ __('pms.audit.postings') }}</th><th class="text-right">{{ __('pms.audit.net') }}</th><th class="text-right">{{ __('pms.audit.tax') }}</th><th class="text-right">{{ __('pms.audit.gross') }}</th></tr></thead>
                        <tbody>
                        @foreach ($revenue as $row)
                            <tr><td class="font-semibold">{{ $row->code }}</td><td>{{ $row->name }}</td><td class="text-right">{{ $row->postings }}</td>
                                <td class="text-right tabular-nums">{{ $money($row->net) }}</td><td class="text-right tabular-nums">{{ $money($row->tax) }}</td><td class="text-right tabular-nums">{{ $money($row->gross) }}</td></tr>
                        @endforeach
                        <tr class="font-semibold"><td colspan="3"></td><td class="text-right tabular-nums">{{ $money($revenue->sum('net')) }}</td><td class="text-right tabular-nums">{{ $money($revenue->sum('tax')) }}</td><td class="text-right tabular-nums">{{ $money($revenue->sum('gross')) }}</td></tr>
                        </tbody>
                    </table>
                @endif
            </section>
        @endif

        <section class="win !min-h-fit p-3 shrink-0">
            <b>{{ __('pms.audit.history') }}</b>
            <table class="grid-table mt-1">
                <thead><tr><th>{{ __('pms.audit.date') }}</th><th>{{ __('pms.audit.status') }}</th><th>{{ __('pms.audit.started') }}</th><th>{{ __('pms.audit.finished') }}</th><th>{{ __('pms.audit.user') }}</th></tr></thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr><td class="font-semibold">{{ \Illuminate\Support\Carbon::parse($run->business_date)->format('d.m.Y') }}</td><td>{{ __('pms.audit.run_status.'.$run->status) }}</td>
                        <td>{{ $run->started_at ? \Illuminate\Support\Carbon::parse($run->started_at)->format('d.m H:i') : '' }}</td>
                        <td>{{ $run->finished_at ? \Illuminate\Support\Carbon::parse($run->finished_at)->format('d.m H:i') : '' }}</td><td>{{ $run->username }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted">{{ __('pms.common.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@if ($canOverride)
    <form id="na-answers" method="POST" action="{{ route('night-audit.answers') }}" hidden>@csrf @method('PUT')</form>
@endif
@endsection
