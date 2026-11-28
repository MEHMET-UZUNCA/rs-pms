@extends('layouts.print')
@section('title', __('pms.reports.r.'.$key))
@section('back', route('reports.index', request()->except('format')))
@section('doc-title')<b>{{ __('pms.reports.r.'.$key) }}</b>
    @if (! empty($archive))<span class="small">{{ __('pms.reports.archive_short') }}</span><br>@endif
    <span class="small muted">@if (in_array('range', $report['filters'], true)){{ $filters['from']->format('d.m.Y') }} – {{ $filters['to']->format('d.m.Y') }}@else{{ $filters['from']->format('d.m.Y') }}@endif</span>@endsection
@section('content')
    @if (! empty($report['landscape']))<style>@page { size: A4 landscape; } .page { width: 297mm; } table { font-size: 8.5pt; }</style>@endif
    @include('reports._table', ['class' => ''])
    <p class="small muted" style="margin-top:8px">{{ __('pms.reports.footer', ['currency' => $base]) }}</p>
@endsection
