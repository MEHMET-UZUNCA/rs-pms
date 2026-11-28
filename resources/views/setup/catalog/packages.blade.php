@extends('setup.catalog.layout', ['title' => __('pms.setup.packages'), 'subtitle' => __('pms.catalog.packages_sub')])
@section('catalog')
@php $P = \App\Http\Controllers\Setup\PackageController::class; $s = $selected; @endphp
<div class="grid gap-4 xl:grid-cols-[22rem_1fr]">
    <div>
        <table class="grid-table" data-catalog="packages">
            <thead><tr><th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="text-right">{{ __('pms.catalog.rate_codes') }}</th></tr></thead>
            <tbody>
            @foreach ($packages as $pk)
                <tr @class(['is-selected' => $s && $s->id === $pk->id])><td><a class="font-semibold text-brand-600 hover:underline" href="{{ route('setup.packages.index', ['paket' => $pk->id]) }}">{{ $pk->code }}</a></td>
                    <td>{{ $pk->name }} <span class="text-[11px] text-muted">{{ __('pms.catalog.package_types.'.$pk->type) }}</span></td><td class="text-right">{{ $rates[$pk->id] ?? 0 }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <a href="{{ route('setup.packages.index', ['paket' => 'yeni']) }}" class="btn mt-2" data-hotkey="alt+n">{{ __('pms.common.new') }} <span class="kbd">Alt+N</span></a>
    </div>

    <div class="flex flex-col gap-4 min-w-0">
        @php $isNew = request('paket') === 'yeni' || ! $s; $pkg = $isNew ? null : $s; @endphp
        <form method="POST" action="{{ $pkg ? route('setup.packages.update', $pkg->id) : route('setup.packages.store') }}" class="win !min-h-fit p-3 grid grid-cols-2 md:grid-cols-4 gap-3" data-package-form>
            @csrf @if ($pkg) @method('PUT') @endif
            <b class="col-span-full">{{ $pkg ? $pkg->code.' — '.$pkg->name : __('pms.catalog.new_package') }}</b>
            <div><label class="field-label" for="pk-code">{{ __('pms.common.code') }}</label><input id="pk-code" name="code" value="{{ old('code', $pkg?->code) }}" maxlength="20" class="input uppercase" required></div>
            <div class="md:col-span-3"><label class="field-label" for="pk-name">{{ __('pms.common.name') }}</label><input id="pk-name" name="name" value="{{ old('name', $pkg?->name) }}" maxlength="100" class="input" required></div>
            <div><label class="field-label" for="pk-type">{{ __('pms.catalog.type') }}</label><select id="pk-type" name="type" class="input">@foreach ($P::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $pkg?->type ?? 'board') === $t)>{{ __('pms.catalog.package_types.'.$t) }}</option>@endforeach</select></div>
            <div><label class="field-label" for="pk-rhythm">{{ __('pms.catalog.rhythm') }}</label><select id="pk-rhythm" name="posting_rhythm" class="input">@foreach ($P::RHYTHMS as $t)<option value="{{ $t }}" @selected(old('posting_rhythm', $pkg?->posting_rhythm ?? 'every_night') === $t)>{{ __('pms.catalog.rhythms.'.$t) }}</option>@endforeach</select></div>
            <div class="md:col-span-2 flex flex-wrap items-end gap-x-4 gap-y-1">
                <label class="flex items-center gap-1.5"><input type="checkbox" name="is_included_in_rate" value="1" @checked($pkg ? $pkg->is_included_in_rate : true)> {{ __('pms.catalog.included') }}</label>
                @foreach (['includes_breakfast' => 'breakfast', 'includes_lunch' => 'lunch', 'includes_dinner' => 'dinner'] as $field => $meal)
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="{{ $field }}" value="1" @checked($pkg?->{$field})> {{ __('pms.catalog.meals.'.$meal) }}</label>
                @endforeach
            </div>
            <div class="col-span-full flex gap-2">
                <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.common.save') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
                @if ($pkg)<button type="submit" form="pk-delete" class="btn">{{ __('pms.common.delete') }}</button>@endif
            </div>
        </form>
        @if ($pkg)<form method="POST" action="{{ route('setup.packages.destroy', $pkg->id) }}" id="pk-delete" data-confirm="{{ __('pms.catalog.confirm_delete', ['code' => $pkg->code]) }}">@csrf @method('DELETE')</form>@endif

        @if ($pkg)
            <div class="win !min-h-fit p-3">
                <b>{{ __('pms.catalog.elements') }}</b>
                <table class="grid-table mt-1" data-package-elements>
                    <thead><tr><th>{{ __('pms.catalog.department_code') }}</th><th class="w-40">{{ __('pms.catalog.calc') }}</th><th class="w-28 text-right">{{ __('pms.catalog.adult') }}</th><th class="w-28 text-right">{{ __('pms.catalog.child') }}</th>
                        <th class="w-20 text-center" title="{{ __('pms.catalog.allowance_hint') }}">{{ __('pms.catalog.allowance') }}</th><th class="w-36"></th></tr></thead>
                    <tbody>
                    @foreach ($elements->push(null) as $e)
                        @php $f = $e ? 'e-'.$e->id : 'e-new'; @endphp
                        <tr @class(['bg-brand-50/40' => ! $e])>
                            <td><select form="{{ $f }}" name="transaction_code_id" class="input" aria-label="{{ __('pms.catalog.department_code') }}">@foreach ($codes as $c)<option value="{{ $c->id }}" @selected($e && (int) $e->transaction_code_id === (int) $c->id)>{{ $c->code }} {{ $c->name }}</option>@endforeach</select></td>
                            <td><select form="{{ $f }}" name="calc" class="input" aria-label="{{ __('pms.catalog.calc') }}">@foreach ($P::CALCS as $c)<option value="{{ $c }}" @selected(($e?->calc ?? 'per_person') === $c)>{{ __('pms.catalog.calcs.'.$c) }}</option>@endforeach</select></td>
                            <td><input form="{{ $f }}" name="amount_adult" value="{{ $e ? number_format((float) $e->amount_adult, 2, ',', '') : '' }}" inputmode="decimal" class="input text-right" aria-label="{{ __('pms.catalog.adult') }}"></td>
                            <td><input form="{{ $f }}" name="amount_child" value="{{ $e ? number_format((float) $e->amount_child, 2, ',', '') : '' }}" inputmode="decimal" class="input text-right" aria-label="{{ __('pms.catalog.child') }}"></td>
                            <td class="text-center"><input type="checkbox" form="{{ $f }}" name="is_allowance" value="1" @checked($e?->is_allowance) aria-label="{{ __('pms.catalog.allowance') }}"></td>
                            <td class="whitespace-nowrap">
                                @if ($e)
                                    <form method="POST" action="{{ route('setup.packages.elements.update', [$pkg->id, $e->id]) }}" id="{{ $f }}" class="inline">@csrf @method('PUT')<button class="btn !h-7">{{ __('pms.common.ok') }}</button></form>
                                    <form method="POST" action="{{ route('setup.packages.elements.destroy', [$pkg->id, $e->id]) }}" class="inline">@csrf @method('DELETE')<button class="btn !h-7">{{ __('pms.common.delete') }}</button></form>
                                @else
                                    <form method="POST" action="{{ route('setup.packages.elements.store', $pkg->id) }}" id="e-new">@csrf<button class="btn btn-primary !h-7">{{ __('pms.common.new') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-[12.5px] text-muted mt-2">{{ __('pms.catalog.packages_hint') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
