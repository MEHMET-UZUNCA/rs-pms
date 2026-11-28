@extends('setup.catalog.layout', ['title' => __('pms.setup.rate_categories'), 'subtitle' => __('pms.catalog.categories_sub')])
@section('catalog')
<table class="grid-table max-w-3xl" data-catalog="categories">
    <thead><tr><th class="w-32">{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="w-20 text-right">{{ __('pms.rtypes.sort') }}</th><th class="w-24 text-right">{{ __('pms.catalog.rate_codes') }}</th><th class="w-36"></th></tr></thead>
    <tbody>
    @foreach ($rows->push(null) as $r)
        @php $f = $r ? 'c-'.$r->id : 'c-new'; @endphp
        <tr @class(['bg-brand-50/40' => ! $r])>
            <td><input form="{{ $f }}" name="code" value="{{ $r?->code ?? old('code') }}" maxlength="20" class="input uppercase" aria-label="{{ __('pms.common.code') }}"></td>
            <td><input form="{{ $f }}" name="name" value="{{ $r?->name ?? old('name') }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
            <td><input form="{{ $f }}" name="sort" value="{{ $r?->sort ?? 0 }}" inputmode="numeric" class="input text-right" aria-label="{{ __('pms.rtypes.sort') }}"></td>
            <td class="text-right">{{ $r ? ($used[$r->id] ?? 0) : '' }}</td>
            <td class="whitespace-nowrap">@include('setup.catalog._row-actions', ['row' => $r, 'form' => $f, 'save' => $r ? route('setup.categories.update', $r->id) : route('setup.categories.store'), 'delete' => $r ? route('setup.categories.destroy', $r->id) : null])</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
