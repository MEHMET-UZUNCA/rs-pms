@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="p-3 flex flex-wrap gap-3">
    <div class="win flex-1 basis-64 p-3 text-[13px]">
        <div class="font-semibold mb-1.5">{{ $folio->folio_number }} · {{ $folio->name }}</div>
        <div><span class="text-muted">Geçerlilik:</span> {{ $folio->valid_from?->format('d.m.Y') }} – {{ $folio->valid_until?->format('d.m.Y') }}</div>
        <div><span class="text-muted">Bakiye:</span> <b>{{ $money($folio->balance) }}</b></div>
        @if ($folio->notes)<div><span class="text-muted">Not:</span> {{ $folio->notes }}</div>@endif
    </div>
    <div class="win flex-[3] basis-96 p-3">
        <table class="grid-table">
            <thead><tr><th>Tarih</th><th>Kod</th><th>Açıklama</th><th class="text-right">Tutar</th></tr></thead>
            <tbody>
            @forelse ($postings as $p)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($p->business_date)->format('d.m.Y') }}</td><td>{{ $p->code }}</td><td>{{ $p->description ?? $p->name }}</td><td class="text-right tabular-nums">{{ $money($p->amount) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-muted">Kayıt yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
