@extends('layouts.print')
@php $m = fn ($x) => number_format((float) $x, 2, ',', '.'); @endphp
@section('title', __('pms.invoice.folio').' '.$folio->folio_number)
@section('back', $back)
@section('doc-title')<b>{{ mb_strtoupper(__('pms.invoice.folio')) }}</b>{{ __('pms.invoice.info_copy') }}<br>{{ __('pms.invoice.no') }} {{ $folio->folio_number }}@if ($folio->reservation_id) · {{ __('pms.billing.window', ['n' => $folio->window]) }}@endif @endsection
@section('content')
<dl class="meta">
    <div><dt>{{ __('pms.invoice.guest') }}</dt><dd>{{ $billTo['name'] }}</dd></div>
    @if ($reservation)
        <div><dt>{{ __('pms.card.room') }}</dt><dd>{{ $reservation->room?->number }} {{ $reservation->roomType?->code }}</dd></div>
        <div><dt>{{ __('pms.res.arrival') }} / {{ __('pms.res.departure') }}</dt><dd>{{ $reservation->arrival_date->format('d.m.Y') }} – {{ $reservation->departure_date->format('d.m.Y') }}</dd></div>
        <div><dt>{{ __('pms.res.conf_no') }}</dt><dd>{{ $reservation->confirmation_number }}</dd></div>
    @else
        <div><dt>{{ __('pms.common.name') }}</dt><dd>{{ $folio->name }}</dd></div>
    @endif
    @if ($billTo['address'])<div><dt>{{ __('pms.prof.address') }}</dt><dd>{{ $billTo['address'] }}</dd></div>@endif
</dl>
<table>
    <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.billing.department_code') }}</th><th>{{ __('pms.fixed.description') }}</th><th class="r">{{ __('pms.billing.qty') }}</th><th class="r">{{ __('pms.invoice.charges') }}</th><th class="r">{{ __('pms.invoice.payments') }}</th></tr></thead>
    <tbody>
    @foreach ($postings as $p)
        <tr><td>{{ \Illuminate\Support\Carbon::parse($p->business_date)->format('d.m.Y') }}</td><td>{{ $p->code }}</td>
            <td>{{ $p->description }}@if ($p->foreign_currency) <span class="muted small">({{ $p->foreign_currency }} {{ $m(abs($p->foreign_amount)) }})</span>@endif</td>
            <td class="r">{{ $p->amount > 0 ? rtrim(rtrim(number_format((float) $p->quantity, 2, ',', ''), '0'), ',') : '' }}</td>
            <td class="r">{{ $p->amount > 0 ? $m($p->amount) : '' }}</td><td class="r">{{ $p->amount < 0 ? $m(-$p->amount) : '' }}</td></tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    <tr><td>{{ __('pms.invoice.total_charges') }}</td><td class="r">{{ $m($charges) }}</td></tr>
    <tr><td>{{ __('pms.invoice.total_payments') }}</td><td class="r">{{ $m($payments) }}</td></tr>
    <tr class="big"><td>{{ __('pms.billing.balance') }} ({{ $currency }})</td><td class="r">{{ $m($folio->balance) }}</td></tr>
</table>
@if ($taxes)
    <table class="totals small"><thead><tr><th>{{ __('pms.invoice.vat') }}</th><th class="r">{{ __('pms.invoice.base') }}</th><th class="r">{{ __('pms.invoice.vat_amount') }}</th></tr></thead>
        @foreach ($taxes as $t)<tr><td>%{{ rtrim(rtrim(number_format($t['rate'], 2, ',', ''), '0'), ',') }}</td><td class="r">{{ $m($t['base']) }}</td><td class="r">{{ $m($t['tax']) }}</td></tr>@endforeach</table>
@endif
<p class="small muted">{{ __('pms.invoice.info_note') }}</p>
<div class="sign"><span>{{ __('pms.invoice.guest_signature') }}</span><span>{{ __('pms.invoice.cashier') }}</span></div>
@endsection
