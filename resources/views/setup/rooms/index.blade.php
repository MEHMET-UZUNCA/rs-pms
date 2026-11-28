@extends('layouts.app')
@section('title', __('pms.rooms_setup.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.rooms_setup.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.records', ['count' => $rooms->count()]) }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-lg:hidden">@include('setup._tree')</aside>
        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <input type="search" class="input w-32" placeholder="{{ __('pms.rooms_setup.number') }}" data-grid-filter="room-grid" aria-label="{{ __('pms.rooms_setup.number') }}" autofocus>
                <select name="tip" class="input w-44" onchange="this.form.submit()" aria-label="{{ __('pms.res.room_type') }}"><option value="">{{ __('pms.rooms_setup.all_types') }}</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}" @selected($type === (int) $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach</select>
                <select name="kat" class="input w-36" onchange="this.form.submit()" aria-label="{{ __('pms.hkscreen.floor') }}"><option value="">{{ __('pms.hkm.all_floors') }}</option>
                    @foreach ($floors as $f)<option value="{{ $f->id }}" @selected($floor === (int) $f->id)>{{ $f->name }}</option>@endforeach</select>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($inactive) onchange="this.form.submit()"> {{ __('pms.rooms_setup.show_inactive') }}</label>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table min-w-[48rem]" id="room-grid" data-grid data-selected="{{ session('selected', 0) }}">
                    <thead><tr><th>{{ __('pms.rooms_setup.number') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.rooms_setup.building') }}</th><th>{{ __('pms.hkscreen.floor') }}</th>
                        <th class="text-right">{{ __('pms.rtypes.max_occupancy_short') }}</th><th>{{ __('pms.rooms_setup.features') }}</th><th>{{ __('pms.rooms_setup.flags') }}</th></tr></thead>
                    <tbody>
                    @foreach ($rooms as $r)
                        <tr data-id="{{ $r->id }}" data-url-edit="{{ route('setup.rooms.edit', $r->id) }}" @class(['is-inactive' => ! $r->is_active])>
                            <td class="font-semibold">{{ $r->number }}@if ($r->name) <span class="font-normal text-muted">{{ $r->name }}</span>@endif</td>
                            <td>{{ $r->type_code }}</td><td>{{ $r->building_name }}</td><td>{{ $r->floor_name }}</td><td class="text-right">{{ $r->max_occupancy }}</td>
                            <td class="text-[12px]">{{ ($features[$r->id] ?? collect())->pluck('code')->implode(', ') }}</td>
                            <td class="text-[12px]">{{ collect(['is_smoking' => 'smoking', 'is_accessible' => 'accessible', 'is_pseudo' => 'pseudo'])->filter(fn ($l, $f) => $r->{$f})->map(fn ($l) => __('pms.rooms_setup.'.$l))->implode(', ') }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($rooms->isNotEmpty()) hidden @endif><td colspan="7" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="w-44 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.rooms.create') }}" class="btn btn-side" data-hotkey="alt+n">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="room-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Enter</span></a>
            <a href="{{ route('setup.room-types.index') }}" class="btn btn-side" data-hotkey="alt+t">{{ __('pms.setup.room_types') }} <span class="kbd ml-auto">Alt+T</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
