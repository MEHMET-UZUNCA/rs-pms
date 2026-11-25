@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="flex flex-wrap gap-3 p-3">
    <div class="win flex-1 basis-64 p-3">
        <div class="font-semibold mb-1.5">{{ $r->profile?->display_name }} @if ($r->profile?->nationality_code)<span class="text-muted font-normal">· {{ $r->profile->nationality_code }}</span>@endif</div>
        <ul class="flex flex-col gap-1 text-[13px]">
            <li><span class="text-muted">{{ __('pms.res.contact') }}:</span>
                @forelse ($r->profile?->communications ?? [] as $c){{ $c->value }}@if (! $loop->last), @endif @empty <span class="text-muted">—</span>@endforelse</li>
            <li><span class="text-muted">{{ __('pms.res.address') }}:</span>
                @if ($a = $r->profile?->addresses?->first()){{ trim($a->line1.' '.$a->city.' '.$a->country_code) }}@else<span class="text-muted">—</span>@endif</li>
            <li><span class="text-muted">{{ __('pms.res.profiles') }}:</span> @forelse ($profiles as $name){{ $name }}@if (! $loop->last), @endif @empty <span class="text-muted">—</span>@endforelse</li>
            <li><span class="text-muted">{{ __('pms.res.folios') }}:</span>
                @forelse ($r->folios->sortBy('window') as $f)<span class="whitespace-nowrap">{{ __('pms.res.window', ['n' => $f->window]) }} {{ $money($f->balance) }}</span>@if (! $loop->last) · @endif @empty <span class="text-muted">{{ __('pms.res.no_folio') }}</span>@endforelse</li>
            <li><span class="text-muted">{{ __('pms.res.history') }}:</span> {{ $history }}</li>
            @if ($r->guest_comments)<li><span class="text-muted">{{ __('pms.res.note') }}:</span> {{ $r->guest_comments }}</li>@endif
        </ul>
    </div>
    <div class="win flex-[3] basis-96 p-3 grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-2 text-[13px]">
        <div><div class="text-muted">{{ __('pms.res.room_type_short') }}</div><div class="font-semibold">{{ $r->room?->number ?? __('pms.res.not_assigned') }} · {{ $r->roomType?->code }}</div></div>
        <div><div class="text-muted">{{ __('pms.res.stay') }}</div><div class="font-semibold">{{ $r->arrival_date?->format('d.m.Y') }} – {{ $r->departure_date?->format('d.m.Y') }} ({{ __('pms.res.nights_count', ['n' => $r->nights]) }})</div></div>
        <div><div class="text-muted">{{ __('pms.res.rate_code') }}</div><div class="font-semibold">{{ $r->rateCode?->code }} · {{ $money($r->total_room_amount) }} {{ $r->currency_code }}</div></div>
        <div><div class="text-muted">{{ __('pms.common.balance') }}</div><div class="font-semibold">{{ $money($r->folios->sum('balance')) }}</div></div>
        <div><div class="text-muted">{{ __('pms.res.pax') }}</div><div class="font-semibold">{{ trans_choice('pms.res.adults', $r->adults) }}@if ($r->children) + {{ trans_choice('pms.res.children', $r->children) }}@endif</div></div>
        <div><div class="text-muted">{{ __('pms.res.status') }}</div><div class="font-semibold">{{ $statusLabel }} · {{ $r->reservationType?->name }}</div></div>
        <div><div class="text-muted">{{ __('pms.res.conf_no') }}</div><div class="font-semibold">{{ $r->confirmation_number }}</div></div>
        <div><div class="text-muted">{{ __('pms.res.simphony') }}</div><div class="font-semibold">{{ $r->room_charges_allowed ? __('pms.res.room_charges_open') : __('pms.res.room_charges_closed') }}</div></div>
    </div>
</div>
