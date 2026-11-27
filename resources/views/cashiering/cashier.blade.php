@extends('layouts.app')
@section('title', __('pms.cashier.title'))
@section('content')
@php $m = fn ($x) => number_format((float) $x, 2, ',', '.'); $d = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y H:i') : ''; @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.cashier.title') }}</span>
        <span class="font-normal text-brand-100 text-[12px]">@if ($cashier){{ __('pms.cashier.cashier') }} #{{ $cashier->number }} {{ $cashier->name }}@endif</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-[3fr_2fr]">
        <section class="win !min-h-fit p-3 min-w-0">
            @if ($shift)
                <form method="POST" action="{{ route('cashier.close') }}" data-confirm="{{ __('pms.cashier.close_confirm') }}">
                    @csrf
                    <div class="flex flex-wrap items-center gap-2 mb-2"><b>{{ __('pms.cashier.open_shift') }}</b>
                        <span class="text-muted text-[12px]">{{ __('pms.cashier.opened') }} {{ $d($shift->opened_at) }} · {{ __('pms.common.business_date') }} {{ \Illuminate\Support\Carbon::parse($shift->business_date)->format('d.m.Y') }}</span>
                        <a href="{{ route('cashier.report', $shift->id) }}" class="btn ml-auto" data-hotkey="alt+r">{{ __('pms.cashier.preview') }} <span class="kbd">Alt+R</span></a></div>
                    <div class="overflow-x-auto"><table class="grid-table min-w-[36rem]" data-shift-totals>
                        <thead><tr><th>{{ __('pms.billing.payment_code') }}</th><th>{{ __('pms.cashier.currency') }}</th><th class="text-right">{{ __('pms.cashier.count') }}</th>
                            <th class="text-right">{{ __('pms.cashier.system') }}</th><th class="text-right w-36">{{ __('pms.cashier.counted') }}</th><th class="text-right">{{ __('pms.cashier.base_amount') }}</th></tr></thead>
                        <tbody>
                        @forelse ($totals as $t)
                            @php $key = $t->transaction_code_id.'|'.$t->currency_code; @endphp
                            <tr><td class="font-semibold">{{ $t->code }} {{ $t->name }}</td><td>{{ $t->currency_code }}</td><td class="text-right">{{ $t->count }}</td>
                                <td class="text-right tabular-nums" data-system="{{ $t->code }}-{{ $t->currency_code }}">{{ $m($t->system_amount) }}</td>
                                <td><input name="counted[{{ $key }}]" value="{{ old('counted.'.$key) }}" inputmode="decimal" class="input text-right @error('counted.'.$key) input-error @enderror"
                                    placeholder="{{ number_format($t->system_amount, 2, ',', '') }}" aria-label="{{ __('pms.cashier.counted') }} {{ $t->code }} {{ $t->currency_code }}" @if ($t->payment_kind !== 'cash' && $t->payment_kind !== 'foreign_cash') tabindex="-1" @endif></td>
                                <td class="text-right tabular-nums text-muted">{{ $m($t->base_amount) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('pms.cashier.no_payments') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    <p class="text-[12px] text-muted mt-1">{{ __('pms.cashier.count_hint') }}</p>
                    @if ($charges->isNotEmpty())
                        <b class="block mt-3">{{ __('pms.cashier.charges') }}</b>
                        <table class="grid-table mt-1"><tbody>@foreach ($charges as $c)<tr><td>{{ $c->code }} {{ $c->name }}</td><td class="text-right">{{ $c->cnt }}</td><td class="text-right tabular-nums">{{ $m($c->amount) }}</td></tr>@endforeach</tbody></table>
                    @endif
                    <label class="block mt-3"><span class="field-label">{{ __('pms.cashier.notes') }}</span><input name="notes" value="{{ old('notes') }}" maxlength="500" class="input"></label>
                    <div class="flex mt-3"><button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.cashier.close') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button></div>
                </form>
            @else
                <p class="text-muted">{{ __('pms.cashier.no_open') }}</p>
            @endif
        </section>

        <section class="win !min-h-fit p-3 min-w-0">
            <b>{{ $all ? __('pms.cashier.recent_all') : __('pms.cashier.recent') }}</b>
            <div class="overflow-x-auto"><table class="grid-table mt-1 min-w-[26rem]">
                <thead><tr><th>{{ __('pms.cashier.cashier') }}</th><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.res.status') }}</th><th>{{ __('pms.cashier.closed') }}</th><th class="text-right">{{ __('pms.cashier.difference') }}</th></tr></thead>
                <tbody>
                @forelse ($recent as $s)
                    <tr><td><a class="text-brand-600 hover:underline" href="{{ route('cashier.report', $s->id) }}">#{{ $s->number }} {{ $s->name }}</a></td><td>{{ \Illuminate\Support\Carbon::parse($s->business_date)->format('d.m.Y') }}</td>
                        <td>{{ __('pms.cashier.statuses.'.$s->status) }}</td><td>{{ $d($s->closed_at) }}</td>
                        <td @class(['text-right tabular-nums', 'text-danger font-semibold' => (float) ($differences[$s->id] ?? 0) > 0])>{{ $s->status === 'closed' ? $m($differences[$s->id] ?? 0) : '' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('pms.common.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
