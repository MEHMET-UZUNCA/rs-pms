@extends('layouts.app')
@section('title', __('pms.lf.'.($item->exists ? 'edit' : 'new_'.$item->kind)))
@section('content')
@php $v = fn (string $f, $default = null) => old($f, $default ?? $item->{$f}); @endphp
<form method="POST" action="{{ $item->exists ? route('lost-found.update', $item) : route('lost-found.store', $item->kind) }}" class="win flex-1">
    @csrf
    @if ($item->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $item->exists ? __('pms.lf.edit').' '.$item->number : __('pms.lf.new_'.$item->kind) }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <section class="p-3 grid gap-2.5 max-w-2xl grid-cols-2">
        <div class="col-span-2"><label class="field-label" for="description">{{ __('pms.lf.item') }} *</label>
            <input id="description" name="description" value="{{ $v('description') }}" maxlength="500" class="input @error('description') input-error @enderror" required autofocus></div>
        <div><label class="field-label" for="group_id">{{ __('pms.lf.group') }}</label>
            <select id="group_id" name="group_id" class="input"><option value=""></option>@foreach ($groups as $g)<option value="{{ $g->id }}" @selected((string) $v('group_id') === (string) $g->id)>{{ $g->name }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="field-label" for="found_date">{{ __('pms.lf.when') }} *</label>
                <input id="found_date" name="found_date" value="{{ old('found_date', $item->found_at?->format('d.m.Y')) }}" class="input @error('found_date') input-error @enderror" required autocomplete="off"></div>
            <div><label class="field-label" for="found_time">{{ __('pms.lf.time') }}</label>
                <input id="found_time" name="found_time" type="time" value="{{ old('found_time', $item->found_at?->format('H:i')) }}" class="input"></div>
        </div>
        <div><label class="field-label" for="room">{{ __('pms.lf.room') }}</label>
            <input id="room" name="room" value="{{ old('room', $item->room?->number) }}" maxlength="10" class="input @error('room') input-error @enderror" autocomplete="off"></div>
        <div><label class="field-label" for="public_place_id">{{ __('pms.lf.place') }}</label>
            <select id="public_place_id" name="public_place_id" class="input"><option value=""></option>@foreach ($places as $g)<option value="{{ $g->id }}" @selected((string) $v('public_place_id') === (string) $g->id)>{{ $g->name }}</option>@endforeach</select></div>
        <div class="col-span-2"><label class="field-label" for="location">{{ __('pms.lf.where') }}</label>
            <input id="location" name="location" value="{{ $v('location') }}" maxlength="150" class="input"></div>
        <div><label class="field-label" for="person_name">{{ __('pms.lf.'.$item->kind.'_by') }}</label>
            <input id="person_name" name="person_name" value="{{ $v('person_name') }}" maxlength="150" class="input"></div>
        <div><label class="field-label" for="contact">{{ __('pms.lf.contact') }}</label>
            <input id="contact" name="contact" value="{{ $v('contact') }}" maxlength="150" class="input"></div>
        <div><label class="field-label" for="storage_location">{{ __('pms.lf.storage') }}</label>
            <input id="storage_location" name="storage_location" value="{{ $v('storage_location') }}" maxlength="100" class="input"></div>
        <div><label class="field-label" for="note">{{ __('pms.lf.note') }}</label>
            <input id="note" name="note" value="{{ $v('note') }}" maxlength="500" class="input"></div>
    </section>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line mt-auto">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('lost-found.index') }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
