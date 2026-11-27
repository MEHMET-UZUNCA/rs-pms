{{-- Rapor tablosu (ekran ve yazdırma ortak). $result: columns [[kolon, tür]], rows, totals --}}
@php $num = fn ($c) => in_array($c[1], ['money', 'int', 'pct', 'row'], true); @endphp
<table class="{{ $class ?? '' }}" data-report-table>
    <thead><tr>@foreach ($result['columns'] as $c)<th @class(['r text-right' => $num($c)])>{{ __('pms.reports.col.'.$c[0]) }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($result['rows'] as $row)
        <tr>@foreach ($result['columns'] as $c)<td @class(['r text-right tabular-nums whitespace-nowrap' => $num($c), 'whitespace-nowrap tabular-nums' => $c[1] === 'date'])>@include('reports._cell', ['col' => $c, 'row' => $row])</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($result['columns']) }}" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
    @endforelse
    </tbody>
    @if ($result['totals'] && $result['rows'])
        <tfoot><tr class="font-semibold">@foreach ($result['columns'] as $c)<td @class(['r text-right tabular-nums whitespace-nowrap' => $num($c)])>@include('reports._cell', ['col' => $c, 'row' => $result['totals']])</td>@endforeach</tr></tfoot>
    @endif
</table>
