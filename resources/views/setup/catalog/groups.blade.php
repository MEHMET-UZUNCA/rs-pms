@extends('setup.catalog.layout', ['title' => __('pms.setup.department_code_groups'), 'subtitle' => __('pms.catalog.groups_sub')])
@section('catalog')
<table class="grid-table max-w-5xl" data-catalog="groups">
    <thead><tr><th class="w-24">{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="w-40">{{ __('pms.catalog.type') }}</th><th class="w-48">{{ __('pms.catalog.main_group') }}</th>
        <th class="w-20 text-right">{{ __('pms.rtypes.sort') }}</th><th class="w-20 text-right">{{ __('pms.catalog.codes') }}</th><th class="w-36"></th></tr></thead>
    <tbody>
    @foreach ($rows->push(null) as $r)
        @php $f = $r ? 'g-'.$r->id : 'g-new'; @endphp
        <tr @class(['bg-brand-50/40' => ! $r, 'font-semibold' => $r && ! $r->parent_id && $rows->where('parent_id', $r->id)->isNotEmpty()])>
            <td><input form="{{ $f }}" name="code" value="{{ $r?->code ?? old('code') }}" maxlength="10" class="input uppercase" aria-label="{{ __('pms.common.code') }}"></td>
            <td><input form="{{ $f }}" name="name" value="{{ $r?->name ?? old('name') }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
            <td><select form="{{ $f }}" name="type" class="input" aria-label="{{ __('pms.catalog.type') }}">@foreach (\App\Http\Controllers\Setup\CatalogController::GROUP_TYPES as $t)<option value="{{ $t }}" @selected(($r?->type ?? 'revenue') === $t)>{{ __('pms.catalog.group_types.'.$t) }}</option>@endforeach</select></td>
            <td><select form="{{ $f }}" name="parent_id" class="input" aria-label="{{ __('pms.catalog.main_group') }}"><option value="">—</option>@foreach ($parents as $pr)@if (! $r || $pr->id !== $r->id)<option value="{{ $pr->id }}" @selected($r && (int) $r->parent_id === (int) $pr->id)>{{ $pr->code }} {{ $pr->name }}</option>@endif @endforeach</select></td>
            <td><input form="{{ $f }}" name="sort" value="{{ $r?->sort ?? 0 }}" inputmode="numeric" class="input text-right" aria-label="{{ __('pms.rtypes.sort') }}"></td>
            <td class="text-right">@if ($r)<a class="text-brand-600 hover:underline" href="{{ route('setup.transaction-codes.index') }}">{{ $used[$r->id] ?? 0 }}</a>@endif</td>
            <td class="whitespace-nowrap">@include('setup.catalog._row-actions', ['row' => $r, 'form' => $f, 'save' => $r ? route('setup.groups.update', $r->id) : route('setup.groups.store'), 'delete' => $r ? route('setup.groups.destroy', $r->id) : null])</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="text-[12.5px] text-muted mt-2 max-w-5xl">{{ __('pms.catalog.groups_hint') }}</p>
@endsection
