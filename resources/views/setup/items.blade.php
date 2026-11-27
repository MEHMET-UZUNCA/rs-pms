@extends('layouts.app')
@section('title', __('pms.items.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.items.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.items.subtitle') }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex-1 min-h-0 overflow-auto p-3">
        <table class="grid-table max-w-4xl">
            <thead><tr><th class="w-28">{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="w-28 text-right">{{ __('pms.items.total') }}</th><th class="w-32 text-right">{{ __('pms.items.price') }}</th><th class="w-20 text-right">{{ __('pms.items.reservations') }}</th><th class="w-40"></th></tr></thead>
            <tbody>
            @foreach ($items as $i)
                <tr>
                    <td><input form="item-{{ $i->id }}" name="code" value="{{ $i->code }}" maxlength="10" class="input uppercase" aria-label="{{ __('pms.common.code') }}"></td>
                    <td><input form="item-{{ $i->id }}" name="name" value="{{ $i->name }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
                    <td><input form="item-{{ $i->id }}" name="quantity_total" value="{{ $i->quantity_total }}" type="number" min="1" max="999" class="input text-right" aria-label="{{ __('pms.items.total') }}"></td>
                    <td><input form="item-{{ $i->id }}" name="price" value="{{ $i->price !== null ? number_format((float) $i->price, 2, ',', '') : '' }}" inputmode="decimal" class="input text-right" aria-label="{{ __('pms.items.price') }}"></td>
                    <td class="text-right">{{ $inUse[$i->id] ?? 0 }}</td>
                    <td class="whitespace-nowrap">
                        <form method="POST" action="{{ route('setup.items.update', $i->id) }}" id="item-{{ $i->id }}" class="inline">@csrf @method('PUT')<button class="btn !h-7">{{ __('pms.common.ok') }}</button></form>
                        <form method="POST" action="{{ route('setup.items.destroy', $i->id) }}" class="inline" data-confirm="{{ __('pms.items.confirm_delete', ['code' => $i->code]) }}">@csrf @method('DELETE')<button class="btn !h-7">{{ __('pms.common.delete') }}</button></form>
                    </td>
                </tr>
            @endforeach
            <tr class="bg-brand-50/40">
                <td><input form="item-new" name="code" value="{{ old('code') }}" maxlength="10" class="input uppercase" placeholder="BABY" aria-label="{{ __('pms.common.code') }}" autofocus></td>
                <td><input form="item-new" name="name" value="{{ old('name') }}" maxlength="100" class="input" placeholder="{{ __('pms.items.name_hint') }}" aria-label="{{ __('pms.common.name') }}"></td>
                <td><input form="item-new" name="quantity_total" value="{{ old('quantity_total', 1) }}" type="number" min="1" max="999" class="input text-right" aria-label="{{ __('pms.items.total') }}"></td>
                <td><input form="item-new" name="price" value="{{ old('price') }}" inputmode="decimal" class="input text-right" aria-label="{{ __('pms.items.price') }}"></td>
                <td></td>
                <td><form method="POST" action="{{ route('setup.items.store') }}" id="item-new">@csrf<button class="btn btn-primary !h-7">{{ __('pms.common.new') }}</button></form></td>
            </tr>
            </tbody>
        </table>
        <p class="text-[12.5px] text-muted mt-2 max-w-4xl">{{ __('pms.items.hint') }}</p>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
