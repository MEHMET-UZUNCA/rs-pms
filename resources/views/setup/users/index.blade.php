@extends('layouts.app')
@section('title', __('pms.users.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.users.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.users.subtitle') }}</span></div>
    @if ($temp = session('temp_password'))
        <div class="flash m-3 mb-0 border border-amber-300 bg-amber-50 text-[13px]" role="alert" data-temp-password>
            {{ __('pms.users.temp_password', ['username' => $temp['username']]) }} <b class="font-mono text-[15px] select-all">{{ $temp['password'] }}</b>
            <div class="text-muted text-[12px]">{{ __('pms.users.temp_password_hint') }}</div>
        </div>
    @endif
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.users.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">{{ __('pms.common.quick_search') }}
                    <input name="q" value="{{ $search }}" class="input w-56" data-grid-filter="user-grid" placeholder="{{ __('pms.users.username') }} / {{ __('pms.common.name') }} / {{ __('pms.users.role') }}" autocomplete="off" autofocus>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> {{ __('pms.common.show_inactive') }}</label>
                <span class="ml-auto text-muted text-[12px]">{{ __('pms.common.records', ['count' => $users->count()]) }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="user-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th>{{ __('pms.users.username') }}</th><th>{{ __('pms.common.name') }}</th><th>{{ __('pms.users.initials') }}</th><th>{{ __('pms.users.title_field') }}</th>
                        <th>{{ __('pms.users.role') }}</th><th>{{ __('pms.language') }}</th><th>{{ __('pms.users.last_login') }}</th><th>{{ __('pms.users.must_change') }}</th><th>{{ __('pms.common.inactive') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($users as $u)
                        <tr data-id="{{ $u->id }}" @class(['is-inactive' => ! $u->is_active])
                            data-url-edit="{{ route('setup.users.edit', $u->id) }}" data-url-password="{{ route('setup.users.password', $u->id) }}">
                            <td class="font-semibold">{{ $u->username }}</td>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->initials }}</td>
                            <td>{{ $u->title }}</td>
                            <td><span class="font-semibold">{{ $u->role_code }}</span> <span class="text-muted">{{ $u->role_name }}</span></td>
                            <td>{{ strtoupper($u->locale) }}</td>
                            <td>{{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->format('d.m.Y H:i') : '—' }}</td>
                            <td>{{ $u->must_change_password ? '✓' : '' }}</td>
                            <td>{{ $u->is_active ? '' : '✓' }}</td>
                        </tr>
                    @empty
                    @endforelse
                    <tr data-grid-empty @if ($users->isNotEmpty()) hidden @endif><td colspan="9" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.users.create') }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="user-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <form method="POST" data-confirm="{{ __('pms.users.reset_confirm') }}">
                @csrf
                <button class="btn btn-side" data-needs-selection="user-grid" data-action="password" data-hotkey="alt+p">{{ __('pms.users.reset_password') }} <span class="kbd ml-auto">Alt+P</span></button>
            </form>
            <div class="h-3"></div>
            <a href="{{ route('setup.roles.index') }}" class="btn btn-side" data-hotkey="alt+r">{{ __('pms.roles.title') }} <span class="kbd ml-auto">Alt+R</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
