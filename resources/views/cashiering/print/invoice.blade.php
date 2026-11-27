@extends('layouts.print')
@php $m = fn ($x) => number_format((float) $x, 2, ',', '.'); @endphp
@section('title', __('pms.invoice.title').' '.$invoice->invoice_number)
@section('back', $back)
@section('doc-title')<b>{{ mb_strtoupper(__('pms.invoice.title')) }}</b>{{ __('pms.invoice.no') }} {{ $invoice->invoice_number }}<br>{{ \Illuminate\Support\Carbon::parse($invoice->business_date)->format('d.m.Y') }}@if ($invoice->status === 'cancelled')<br><b style="color:#b91c1c">{{ __('pms.invoice.cancelled') }}</b>@endif @endsection
@section('content')
<dl class="meta">
    <div><dt>{{ __('pms.invoice.bill_to') }}</dt><dd>{{ $invoice->bill_to_name }}</dd></div>
    @if ($invoice->bill_to_tax_number)<div><dt>{{ __('pms.prof.tax_office') }} / {{ __('pms.prof.tax_number') }}</dt><dd>{{ $invoice->bill_to_tax_office }} {{ $invoice->bill_to_tax_number }}</dd></div>@endif
    @if ($invoice->bill_to_address)<div><dt>{{ __('pms.prof.address') }}</dt><dd>{{ $invoice->bill_to_address }}</dd></div>@endif
    @if ($reservation)
        <div><dt>{{ __('pms.invoice.guest') }}</dt><dd>{{ $reservation->profile?->display_name }}</dd></div>
        <div><dt>{{ __('pms.card.room') }}</dt><dd>{{ $reservation->room?->number }} · {{ $reservation->arrival_date->format('d.m.Y') }} – {{ $reservation->departure_date->format('d.m.Y') }}</dd></div>
        <div><dt>{{ __('pms.res.conf_no') }}</dt><dd>{{ $reservation->confirmation_number }}</dd></div>
    @endif
    <div><dt>{{ __('pms.invoice.folio') }}</dt><dd>{{ $folio->folio_number }}</dd></div>
</dl>
<table>
    <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.fixed.description') }}</th><th class="r">{{ __('pms.billing.qty') }}</th><th class="r">{{ __('pms.invoice.net') }}</th><th class="r">{{ __('pms.invoice.vat') }}</th><th class="r">{{ __('pms.invoice.gross') }}</th></tr></thead>
    <tbody>
    @foreach ($lines as $l)
        <tr><td>{{ $l->stay_date ? \Illuminate\Support\Carbon::parse($l->stay_date)->format('d.m.Y') : '' }}</td><td>{{ $l->description }}</td>
            <td class="r">{{ rtrim(rtrim(number_format((float) $l->quantity, 2, ',', ''), '0'), ',') }}</td><td class="r">{{ $m($l->net_amount) }}</td><td class="r">{{ $m($l->tax_amount) }}</td><td class="r">{{ $m($l->gross_amount) }}</td></tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    @foreach ($taxes as $t)<tr><td>{{ __('pms.invoice.vat') }} %{{ rtrim(rtrim(number_format($t['rate'], 2, ',', ''), '0'), ',') }} ({{ __('pms.invoice.base') }} {{ $m($t['base']) }})</td><td class="r">{{ $m($t['tax']) }}</td></tr>@endforeach
    <tr><td>{{ __('pms.invoice.total_net') }}</td><td class="r">{{ $m($invoice->total_net) }}</td></tr>
    <tr><td>{{ __('pms.invoice.total_vat') }}</td><td class="r">{{ $m($invoice->total_tax) }}</td></tr>
    <tr class="big"><td>{{ __('pms.invoice.total') }} ({{ $invoice->currency_code }})</td><td class="r">{{ $m($invoice->total_gross) }}</td></tr>
    <tr><td class="muted">{{ __('pms.invoice.paid') }}</td><td class="r muted">{{ $m($invoice->total_paid) }}</td></tr>
</table>
<div class="sign"><span>{{ __('pms.invoice.received_by') }}</span><span>{{ __('pms.invoice.issued_by') }}: {{ $issuer }}</span></div>
@endsection
