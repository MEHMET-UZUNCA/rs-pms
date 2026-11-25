@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="flex flex-wrap gap-3 p-3">
    <div class="win flex-1 basis-64 p-3">
        <div class="font-semibold mb-1.5">{{ $r->profile?->display_name }} @if ($r->profile?->nationality_code)<span class="text-muted font-normal">· {{ $r->profile->nationality_code }}</span>@endif</div>
        <ul class="flex flex-col gap-1 text-[13px]">
            <li><span class="text-muted">İletişim:</span>
                @forelse ($r->profile?->communications ?? [] as $c){{ $c->value }}@if (! $loop->last), @endif @empty <span class="text-muted">—</span>@endforelse</li>
            <li><span class="text-muted">Adres:</span>
                @if ($a = $r->profile?->addresses?->first()){{ trim($a->line1.' '.$a->city.' '.$a->country_code) }}@else<span class="text-muted">—</span>@endif</li>
            <li><span class="text-muted">Profiller:</span> @forelse ($profiles as $name){{ $name }}@if (! $loop->last), @endif @empty <span class="text-muted">—</span>@endforelse</li>
            <li><span class="text-muted">Folyolar:</span>
                @forelse ($r->folios->sortBy('window') as $f)<span class="whitespace-nowrap">{{ $f->window }}. pencere {{ $money($f->balance) }}</span>@if (! $loop->last) · @endif @empty <span class="text-muted">yok</span>@endforelse</li>
            <li><span class="text-muted">Geçmiş konaklama:</span> {{ $history }}</li>
            @if ($r->guest_comments)<li><span class="text-muted">Not:</span> {{ $r->guest_comments }}</li>@endif
        </ul>
    </div>
    <div class="win flex-[3] basis-96 p-3 grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-2 text-[13px]">
        <div><div class="text-muted">Oda / Tip</div><div class="font-semibold">{{ $r->room?->number ?? 'atanmadı' }} · {{ $r->roomType?->code }}</div></div>
        <div><div class="text-muted">Konaklama</div><div class="font-semibold">{{ $r->arrival_date?->format('d.m.Y') }} – {{ $r->departure_date?->format('d.m.Y') }} ({{ $r->nights }} gece)</div></div>
        <div><div class="text-muted">Fiyat kodu</div><div class="font-semibold">{{ $r->rateCode?->code }} · {{ $money($r->total_room_amount) }} {{ $r->currency_code }}</div></div>
        <div><div class="text-muted">Bakiye</div><div class="font-semibold">{{ $money($r->folios->sum('balance')) }}</div></div>
        <div><div class="text-muted">Kişi</div><div class="font-semibold">{{ $r->adults }} yetişkin @if ($r->children)+ {{ $r->children }} çocuk @endif</div></div>
        <div><div class="text-muted">Durum</div><div class="font-semibold">{{ $statusLabel }} · {{ $r->reservationType?->name }}</div></div>
        <div><div class="text-muted">Rez. no</div><div class="font-semibold">{{ $r->confirmation_number }}</div></div>
        <div><div class="text-muted">Simphony</div><div class="font-semibold">{{ $r->room_charges_allowed ? 'Oda hesabı açık' : 'Oda hesabı kapalı' }}</div></div>
    </div>
</div>
