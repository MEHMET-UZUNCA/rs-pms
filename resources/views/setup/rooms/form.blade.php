@extends('layouts.app')
@section('title', $room ? __('pms.rooms_setup.edit', ['room' => $room->number]) : __('pms.rooms_setup.new'))
@section('content')
@php $v = fn (string $f, $default = null) => old($f, $room?->{$f} ?? $default); @endphp
<form method="POST" action="{{ $room ? route('setup.rooms.update', $room->id) : route('setup.rooms.store') }}" class="win flex-1" data-room-form>
    @csrf @if ($room) @method('PUT') @endif <input type="hidden" name="_posted" value="1">
    <div class="win-title"><span>{{ $room ? __('pms.rooms_setup.edit', ['room' => $room->number]) : __('pms.rooms_setup.new') }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex-1 min-h-0 overflow-auto p-3">
        <div class="grid gap-3 grid-cols-2 md:grid-cols-4 max-w-4xl">
            <div><label class="field-label" for="number">{{ __('pms.rooms_setup.number') }}</label><input id="number" name="number" value="{{ $v('number') }}" maxlength="10" class="input uppercase" required data-autofocus autofocus></div>
            <div class="col-span-1 md:col-span-3"><label class="field-label" for="name">{{ __('pms.common.name') }}</label><input id="name" name="name" value="{{ $v('name') }}" maxlength="100" class="input"></div>
            <div><label class="field-label" for="room_type_id">{{ __('pms.res.room_type') }}</label>
                <select id="room_type_id" name="room_type_id" class="input" required>@foreach ($types as $t)<option value="{{ $t->id }}" @selected((int) $v('room_type_id', request('tip')) === (int) $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="building_id">{{ __('pms.rooms_setup.building') }}</label>
                <select id="building_id" name="building_id" class="input"><option value="">—</option>@foreach ($buildings as $b)<option value="{{ $b->id }}" @selected((int) $v('building_id') === (int) $b->id)>{{ $b->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="floor_id">{{ __('pms.hkscreen.floor') }}</label>
                <select id="floor_id" name="floor_id" class="input"><option value="">—</option>@foreach ($floors as $f)<option value="{{ $f->id }}" @selected((int) $v('floor_id') === (int) $f->id)>{{ $f->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="hk_section_id">{{ __('pms.rooms_setup.section') }}</label>
                <select id="hk_section_id" name="hk_section_id" class="input"><option value="">—</option>@foreach ($sections as $s)<option value="{{ $s->id }}" @selected((int) $v('hk_section_id') === (int) $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="max_occupancy">{{ __('pms.rtypes.max_occupancy') }}</label><input id="max_occupancy" name="max_occupancy" value="{{ $v('max_occupancy') }}" inputmode="numeric" class="input text-right" placeholder="{{ __('pms.rooms_setup.from_type') }}"></div>
            <div><label class="field-label" for="hk_credits">{{ __('pms.rooms_setup.hk_credits') }}</label><input id="hk_credits" name="hk_credits" value="{{ $v('hk_credits', 1) }}" inputmode="decimal" class="input text-right"></div>
            <div><label class="field-label" for="sort">{{ __('pms.rtypes.sort') }}</label><input id="sort" name="sort" value="{{ $v('sort', 0) }}" inputmode="numeric" class="input text-right"></div>
            <div class="flex flex-col gap-1 justify-end">
                @foreach (['is_active' => true, 'is_smoking' => false, 'is_accessible' => false, 'is_pseudo' => false] as $flag => $default)
                    <label class="flex items-center gap-2"><input type="checkbox" name="{{ $flag }}" value="1" @checked(old('_posted') ? old($flag) : ($room ? $room->{$flag} : $default))> {{ __('pms.rooms_setup.flag.'.$flag) }}</label>
                @endforeach
            </div>
            <div class="col-span-full"><label class="field-label" for="description">{{ __('pms.common.description') }}</label><textarea id="description" name="description" rows="2" class="input !h-auto py-1" maxlength="500">{{ $v('description') }}</textarea></div>
            <fieldset class="col-span-full"><legend class="field-label">{{ __('pms.rooms_setup.features') }}</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">@foreach ($allFeatures as $feat)
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="features[]" value="{{ $feat->id }}" @checked(in_array($feat->id, array_map('intval', old('features', $selected)), true))> <b>{{ $feat->code }}</b> {{ $feat->name }}</label>
                @endforeach</div></fieldset>
        </div>
        <p class="text-[12.5px] text-muted mt-3 max-w-4xl">{{ __('pms.rooms_setup.hint') }}</p>
    </div>
    <div class="flex gap-2 px-3 py-2 border-t border-line">
        @if ($room)<button type="submit" form="room-delete" class="btn">{{ __('pms.common.delete') }}</button>@endif
        <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.rooms.index') }}" class="btn" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@if ($room)<form method="POST" action="{{ route('setup.rooms.destroy', $room->id) }}" id="room-delete" data-confirm="{{ __('pms.rooms_setup.confirm_delete', ['room' => $room->number]) }}">@csrf @method('DELETE')</form>@endif
@endsection
