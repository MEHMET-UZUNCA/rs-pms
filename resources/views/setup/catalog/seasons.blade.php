@extends('setup.catalog.layout', ['title' => __('pms.setup.seasons'), 'subtitle' => __('pms.catalog.seasons_sub')])
@section('catalog')
@php $d = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('d.m.Y'); @endphp
<table class="grid-table max-w-6xl" data-catalog="seasons">
    <thead><tr><th class="w-28">{{ __('pms.common.code') }}</th><th class="w-56">{{ __('pms.common.name') }}</th><th class="w-20">{{ __('pms.catalog.color') }}</th><th class="w-16 text-right">{{ __('pms.rtypes.sort') }}</th>
        <th>{{ __('pms.catalog.date_ranges') }}</th><th class="w-20 text-right">{{ __('pms.catalog.rate_rows') }}</th><th class="w-36"></th></tr></thead>
    <tbody>
    @foreach ($rows->push(null) as $r)
        @php $f = $r ? 's-'.$r->id : 's-new'; @endphp
        <tr @class(['bg-brand-50/40' => ! $r, 'align-top'])>
            <td><input form="{{ $f }}" name="code" value="{{ $r?->code ?? old('code') }}" maxlength="20" class="input uppercase" aria-label="{{ __('pms.common.code') }}"></td>
            <td><input form="{{ $f }}" name="name" value="{{ $r?->name ?? old('name') }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
            <td><input form="{{ $f }}" name="color" type="color" value="{{ $r?->color ?: '#2563eb' }}" class="h-8 w-12 rounded border border-line" aria-label="{{ __('pms.catalog.color') }}"></td>
            <td><input form="{{ $f }}" name="sort" value="{{ $r?->sort ?? 0 }}" inputmode="numeric" class="input text-right" aria-label="{{ __('pms.rtypes.sort') }}"></td>
            <td>
                @if ($r)
                    <div class="flex flex-wrap gap-1.5 items-center">
                        @foreach ($dates[$r->id] ?? [] as $range)
                            <form method="POST" action="{{ route('setup.seasons.dates.destroy', [$r->id, $range->id]) }}" class="inline-flex items-center gap-1 rounded-full bg-surface border border-line pl-2 pr-1 text-[12.5px]">@csrf @method('DELETE')
                                {{ $d($range->date_from) }} – {{ $d($range->date_to) }}<button class="text-muted hover:text-danger px-1" title="{{ __('pms.common.delete') }}">×</button></form>
                        @endforeach
                        <form method="POST" action="{{ route('setup.seasons.dates.store', $r->id) }}" class="inline-flex items-center gap-1">@csrf
                            <input name="date_from" class="input w-24 !h-7" placeholder="{{ __('pms.ooo.from') }}" autocomplete="off" aria-label="{{ __('pms.ooo.from') }}">
                            <input name="date_to" class="input w-24 !h-7" placeholder="{{ __('pms.ooo.to') }}" autocomplete="off" aria-label="{{ __('pms.ooo.to') }}">
                            <button class="btn !h-7">+</button></form>
                    </div>
                @endif
            </td>
            <td class="text-right">{{ $r ? ($used[$r->id] ?? 0) : '' }}</td>
            <td class="whitespace-nowrap">@include('setup.catalog._row-actions', ['row' => $r, 'form' => $f, 'save' => $r ? route('setup.seasons.update', $r->id) : route('setup.seasons.store'), 'delete' => $r ? route('setup.seasons.destroy', $r->id) : null])</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="text-[12.5px] text-muted mt-2 max-w-5xl">{{ __('pms.catalog.seasons_hint') }}</p>
@endsection
