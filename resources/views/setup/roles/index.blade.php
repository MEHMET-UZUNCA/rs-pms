@extends('layouts.app')
@section('title', __('pms.roles.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.roles.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.roles.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.roles.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">{{ __('pms.common.quick_search') }}
                    <input name="q" value="{{ $search }}" class="input w-56" data-grid-filter="role-grid" placeholder="{{ __('pms.common.code') }} / {{ __('pms.common.name') }}" autocomplete="off" autofocus>
                </label>
                <span class="ml-auto text-muted text-[12px]">{{ __('pms.common.records', ['count' => $roles->count()]) }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="role-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr><th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th class="text-right">{{ __('pms.roles.permissions') }}</th><th class="text-right">{{ __('pms.roles.users') }}</th></tr></thead>
                    <tbody>
                    @forelse ($roles as $role)
                        <tr data-id="{{ $role->id }}" data-url-edit="{{ route('setup.roles.edit', $role->id) }}" data-url-copy="{{ route('setup.roles.copy', $role->id) }}"
                            data-url-destroy="{{ route('setup.roles.destroy', $role->id) }}">
                            <td class="font-semibold">{{ $role->code }}</td>
                            <td>{{ $role->name }}</td>
                            <td class="text-right">{{ $role->permissions }} / {{ $total }}</td>
                            <td class="text-right">{{ $role->users }}</td>
                        </tr>
                    @empty
                    @endforelse
                    <tr data-grid-empty @if ($roles->isNotEmpty()) hidden @endif><td colspan="4" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.roles.create') }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="role-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <a class="btn btn-side" data-needs-selection="role-grid" data-action="copy" data-hotkey="alt+c">{{ __('pms.common.copy') }} <span class="kbd ml-auto">Alt+C</span></a>
            <form method="POST" data-confirm="{{ __('pms.roles.delete_confirm') }}">
                @csrf @method('DELETE')
                <button class="btn btn-side" data-needs-selection="role-grid" data-action="destroy" data-hotkey="alt+d delete">{{ __('pms.common.delete') }} <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <div class="h-3"></div>
            <a href="{{ route('setup.users.index') }}" class="btn btn-side" data-hotkey="alt+u">{{ __('pms.users.title') }} <span class="kbd ml-auto">Alt+U</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
