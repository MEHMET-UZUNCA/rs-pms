@extends('layouts.app')
@section('title', __('pms.setup.room_types'))
@section('content')
@php $chk = fn ($t, $f, $default = false) => $t ? (bool) $t->{$f} : $default; @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.setup.room_types') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.rtypes.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-lg:hidden">@include('setup._tree')</aside>
        <div class="flex-1 min-w-0 overflow-auto p-3">
            @if ($errors->any())<div class="flash flash-error mb-2" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            <table class="grid-table min-w-[64rem]" data-room-types>
                <thead><tr>
                    <th class="w-24">{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="w-28">{{ __('pms.rtypes.short_name') }}</th>
                    <th class="w-16 text-right" title="{{ __('pms.rtypes.default_adults') }}">{{ __('pms.rtypes.default_short') }}</th>
                    <th class="w-16 text-right" title="{{ __('pms.rtypes.max_adults') }}">{{ __('pms.rtypes.max_adults_short') }}</th>
                    <th class="w-16 text-right" title="{{ __('pms.rtypes.max_children') }}">{{ __('pms.rtypes.max_children_short') }}</th>
                    <th class="w-16 text-right" title="{{ __('pms.rtypes.max_occupancy') }}">{{ __('pms.rtypes.max_occupancy_short') }}</th>
                    <th class="w-16 text-right">{{ __('pms.rtypes.beds') }}</th><th class="w-16 text-right">{{ __('pms.rtypes.sort') }}</th>
                    <th class="w-14 text-center" title="{{ __('pms.rtypes.pseudo_hint') }}">{{ __('pms.rtypes.pseudo') }}</th>
                    <th class="w-14 text-center">{{ __('pms.rtypes.online') }}</th><th class="w-14 text-center">{{ __('pms.rtypes.active') }}</th>
                    <th class="w-16 text-right">{{ __('pms.rtypes.rooms') }}</th><th class="w-36"></th>
                </tr></thead>
                <tbody>
                @foreach ($types->push(null) as $t)
                    @php $f = $t ? 'rt-'.$t->id : 'rt-new'; @endphp
                    <tr @class(['is-inactive' => $t && ! $t->is_active, 'bg-brand-50/40' => ! $t])>
                        <td><input form="{{ $f }}" name="code" value="{{ $t?->code ?? old('code') }}" maxlength="10" class="input uppercase" aria-label="{{ __('pms.common.code') }}" @unless ($t) placeholder="YMF" @endunless></td>
                        <td><input form="{{ $f }}" name="name" value="{{ $t?->name ?? old('name') }}" maxlength="100" class="input" aria-label="{{ __('pms.common.name') }}"></td>
                        <td><input form="{{ $f }}" name="short_name" value="{{ $t?->short_name }}" maxlength="30" class="input" aria-label="{{ __('pms.rtypes.short_name') }}"></td>
                        @foreach (['default_adults' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3, 'beds' => 2, 'sort' => 0] as $field => $default)
                            <td><input form="{{ $f }}" name="{{ $field }}" value="{{ $t?->{$field} ?? $default }}" inputmode="numeric" class="input text-right" aria-label="{{ __('pms.rtypes.'.$field) }}"></td>
                        @endforeach
                        @foreach (['is_pseudo' => false, 'is_bookable_online' => true, 'is_active' => true] as $field => $default)
                            <td class="text-center"><input type="checkbox" form="{{ $f }}" name="{{ $field }}" value="1" @checked($chk($t, $field, $default)) aria-label="{{ $field }}"></td>
                        @endforeach
                        <td class="text-right tabular-nums">@if ($t)<a class="text-brand-600 hover:underline" href="{{ route('setup.rooms.index', ['tip' => $t->id]) }}">{{ $rooms[$t->id] ?? 0 }}</a>@endif</td>
                        <td class="whitespace-nowrap">
                            @if ($t)
                                <form method="POST" action="{{ route('setup.room-types.update', $t->id) }}" id="{{ $f }}" class="inline">@csrf @method('PUT')<button class="btn !h-7">{{ __('pms.common.ok') }}</button></form>
                                <form method="POST" action="{{ route('setup.room-types.destroy', $t->id) }}" class="inline" data-confirm="{{ __('pms.rtypes.confirm_delete', ['code' => $t->code]) }}">@csrf @method('DELETE')<button class="btn !h-7">{{ __('pms.common.delete') }}</button></form>
                            @else
                                <form method="POST" action="{{ route('setup.room-types.store') }}" id="rt-new">@csrf<button class="btn btn-primary !h-7">{{ __('pms.common.new') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="text-[12.5px] text-muted mt-2 max-w-5xl">{{ __('pms.rtypes.hint') }}</p>
        </div>
    </div>
    <div class="flex px-3 py-2 border-t border-line">
        <a href="{{ route('setup.rooms.index') }}" class="btn" data-hotkey="alt+r">{{ __('pms.rooms_setup.title') }} <span class="kbd">Alt+R</span></a>
        <a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
