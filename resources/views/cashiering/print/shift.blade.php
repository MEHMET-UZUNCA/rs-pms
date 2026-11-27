@extends('layouts.print')
@php $m = fn ($x) => number_format((float) $x, 2, ',', '.'); @endphp
@section('title', __('pms.cashier.report'))
@section('back', route('cashier.index'))
@section('doc-title')<b>{{ mb_strtoupper(__('pms.cashier.report')) }}</b>#{{ $cashier->number }} {{ $cashier->name }}<br>{{ \Illuminate\Support\Carbon::parse($shift->business_date)->format('d.m.Y') }} · {{ __('pms.cashier.statuses.'.$shift->status) }}@endsection
@section('content')
<dl class="meta">
    <div><dt>{{ __('pms.cashier.opened') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($shift->opened_at)->format('d.m.Y H:i') }}</dd></div>
    <div><dt>{{ __('pms.cashier.closed') }}</dt><dd>{{ $shift->closed_at ? \Illuminate\Support\Carbon::parse($shift->closed_at)->format('d.m.Y H:i') : '—' }}</dd></div>
</dl>
<h3>{{ __('pms.cashier.payments') }}</h3>
<table>
    <thead><tr><th>{{ __('pms.billing.payment_code') }}</th><th>{{ __('pms.cashier.currency') }}</th><th class="r">{{ __('pms.cashier.count') }}</th><th class="r">{{ __('pms.cashier.system') }}</th><th class="r">{{ __('pms.cashier.counted') }}</th><th class="r">{{ __('pms.cashier.difference') }}</th><th class="r">{{ __('pms.cashier.base_amount') }}</th></tr></thead>
    <tbody>
    @forelse ($totals as $t)
        @php $saved = $closedTotals[$t->transaction_code_id.'|'.$t->currency_code] ?? null; @endphp
        <tr><td>{{ $t->code }} {{ $t->name }}</td><td>{{ $t->currency_code }}</td><td class="r">{{ $t->count }}</td><td class="r">{{ $m($t->system_amount) }}</td>
            <td class="r">{{ $saved ? $m($saved->counted_amount) : '' }}</td><td class="r">{{ $saved ? $m($saved->difference) : '' }}</td><td class="r">{{ $m($t->base_amount) }}</td></tr>
    @empty
        <tr><td colspan="7" class="muted">{{ __('pms.cashier.no_payments') }}</td></tr>
    @endforelse
    </tbody>
</table>
<h3>{{ __('pms.cashier.charges') }}</h3>
<table>
    <thead><tr><th>{{ __('pms.billing.department_code') }}</th><th class="r">{{ __('pms.cashier.count') }}</th><th class="r">{{ __('pms.fixed.amount') }}</th></tr></thead>
    <tbody>
    @forelse ($charges as $c)<tr><td>{{ $c->code }} {{ $c->name }}</td><td class="r">{{ $c->cnt }}</td><td class="r">{{ $m($c->amount) }}</td></tr>
    @empty<tr><td colspan="3" class="muted">—</td></tr>@endforelse
    </tbody>
</table>
@if ($shift->notes)<p><b>{{ __('pms.cashier.notes') }}:</b> {{ $shift->notes }}</p>@endif
<div class="sign"><span>{{ __('pms.invoice.cashier') }}</span><span>{{ __('pms.cashier.supervisor') }}</span></div>
@endsection
