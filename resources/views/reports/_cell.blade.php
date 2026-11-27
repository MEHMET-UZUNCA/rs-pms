@php
    $type = $col[1] === 'row' ? ($row['type'] ?? 'money') : $col[1];
    $v = $row[$col[0]] ?? null;
@endphp
@if ($v === null || $v === '')
@elseif ($type === 'money'){{ is_numeric($v) ? number_format((float) $v, 2, ',', '.') : $v }}
@elseif ($type === 'pct'){{ is_numeric($v) ? number_format((float) $v, 1, ',', '.').' %' : $v }}
@elseif ($type === 'int'){{ is_numeric($v) ? number_format((float) $v, 0, ',', '.') : $v }}
@elseif ($type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v)){{ \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') }}
@else{{ $v }}
@endif
