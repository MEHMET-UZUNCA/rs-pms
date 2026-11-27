{{-- Değişiklik kaydı satırının alanları: alan: eski → yeni --}}
@php
    $old = json_decode((string) $log->old_values, true) ?: [];
    $new = json_decode((string) $log->new_values, true) ?: [];
    $fmt = fn ($v) => is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)) : (($v === null || $v === '') ? '—' : (string) $v);
@endphp
@foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field)
    <div class="whitespace-normal"><span class="text-muted">{{ $field }}:</span>
        @if (array_key_exists($field, $old))<span class="line-through decoration-danger/50">{{ \Illuminate\Support\Str::limit($fmt($old[$field]), 60) }}</span> → @endif<b class="font-medium">{{ \Illuminate\Support\Str::limit($fmt($new[$field] ?? null), 80) }}</b></div>
@endforeach
@if ($log->note)<div class="text-muted">{{ $log->note }}</div>@endif
