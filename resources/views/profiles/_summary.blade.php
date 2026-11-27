@php
    $addr = $profile->addresses->firstWhere('is_primary', true) ?? $profile->addresses->first();
    $c = fn ($t) => $profile->communications->where('type', $t)->sortByDesc('is_primary')->first()?->value;
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="p-3 text-[13px] grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]" data-profile="{{ $profile->id }}">
    <div class="grid grid-cols-[8rem_1fr] gap-x-2 gap-y-0.5 content-start">
        <span class="text-muted">{{ __('pms.prof.name') }}</span><b>{{ $profile->display_name }} {{ $profile->title }}</b>
        @if ($profile->blacklist_reason)<span class="text-danger">{{ __('pms.prof.statuses.blacklisted') }}</span><span class="text-danger">{{ $profile->blacklist_reason }}</span>@endif
        @if ($addr)<span class="text-muted">{{ __('pms.prof.address') }}</span><span>{{ collect([$addr->line1, $addr->line2, trim($addr->postal_code.' '.$addr->city), $addr->state, $country($addr->country_code)])->filter()->implode(', ') }}</span>@endif
        @foreach (['phone', 'mobile', 'email'] as $t)@if ($c($t))<span class="text-muted">{{ __('pms.prof.'.$t) }}</span><span>{{ $c($t) }}</span>@endif @endforeach
        @if ($profile->nationality_code)<span class="text-muted">{{ __('pms.prof.nationality') }}</span><span>{{ $country($profile->nationality_code) }}</span>@endif
        @if ($profile->birth_date)<span class="text-muted">{{ __('pms.prof.birth_date') }}</span><span>{{ $profile->birth_date->format('d.m.Y') }}</span>@endif
        @if ($profile->tax_number)<span class="text-muted">{{ __('pms.prof.tax_number') }}</span><span>{{ $profile->tax_office }} {{ $profile->tax_number }}</span>@endif
        @if ($profile->commission_pct !== null)<span class="text-muted">{{ __('pms.prof.commission') }}</span><span>%{{ (float) $profile->commission_pct }}</span>@endif
    </div>
    <div>
        <b>{{ __('pms.prof.history') }}</b> <span class="text-muted text-[12px]">{{ $history->count() }}</span>
        <table class="grid-table mt-1"><thead><tr><th>{{ __('pms.res.conf_no') }}</th>@if ($profile->type !== 'individual')<th>{{ __('pms.res.guest') }}</th>@endif<th>{{ __('pms.res.arrival') }}</th><th>{{ __('pms.res.departure') }}</th>
            <th class="text-right">{{ __('pms.res.nights') }}</th><th>{{ __('pms.res.room') }}</th><th>{{ __('pms.res.rate_code') }}</th><th class="text-right">{{ __('pms.history.revenue') }}</th><th>{{ __('pms.res.status') }}</th></tr></thead>
            <tbody>
            @forelse ($history as $h)
                <tr><td>@can('permission', 'reservations.edit')<a class="text-brand-600 hover:underline" href="{{ route('reservations.edit', $h->id) }}">{{ $h->confirmation_number }}</a>@else{{ $h->confirmation_number }}@endcan</td>
                    @if ($profile->type !== 'individual')<td>{{ $h->display_name }}</td>@endif
                    <td>{{ \Illuminate\Support\Carbon::parse($h->arrival_date)->format('d.m.Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($h->departure_date)->format('d.m.Y') }}</td>
                    <td class="text-right">{{ $h->nights }}</td><td>{{ $h->room }}</td><td>{{ $h->rate_code }}</td><td class="text-right tabular-nums">{{ $money($h->total_room_amount) }}</td><td>{{ __('pms.res.statuses.'.$h->status) }}</td></tr>
            @empty
                <tr><td colspan="9" class="text-muted">{{ __('pms.prof.no_history') }}</td></tr>
            @endforelse
            </tbody></table>
    </div>
</div>
