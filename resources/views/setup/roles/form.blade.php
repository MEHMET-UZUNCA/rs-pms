@extends('layouts.app')
@section('title', $role->id ? __('pms.roles.edit_title', ['name' => $role->code]) : __('pms.roles.new_title'))
@section('content')
@php $checked = array_map('intval', old('permissions', $assigned)); @endphp
<form method="POST" action="{{ $role->id ? route('setup.roles.update', $role->id) : route('setup.roles.store') }}" class="win flex-1">
    @csrf
    @if ($role->id) @method('PUT') @endif
    <div class="win-title"><span>{{ $role->id ? __('pms.roles.edit_title', ['name' => $role->code]) : __('pms.roles.new_title') }}</span></div>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="flex flex-1 min-h-0 max-lg:flex-col">
        <section class="w-80 shrink-0 p-3 flex flex-col gap-2.5 border-r border-line max-lg:w-full max-lg:border-r-0 max-lg:border-b">
            <div>
                <label class="field-label" for="code">{{ __('pms.common.code') }} *</label>
                <input id="code" name="code" value="{{ old('code', $role->code) }}" maxlength="40" class="input uppercase @error('code') input-error @enderror" required autofocus>
            </div>
            <div>
                <label class="field-label" for="name">{{ __('pms.common.name') }} *</label>
                <input id="name" name="name" value="{{ old('name', $role->name) }}" maxlength="100" class="input @error('name') input-error @enderror" required>
            </div>
            <p class="text-[12px] text-muted">{{ __('pms.roles.hint') }}</p>
        </section>

        <section class="flex-1 min-w-0 min-h-0 overflow-auto p-3 grid gap-3 content-start md:grid-cols-2">
            @foreach ($permissions as $group => $items)
                <table class="grid-table border border-line rounded" data-permission-group="{{ $group }}">
                    <thead><tr><th class="w-8"><input type="checkbox" data-check-all aria-label="{{ __('pms.roles.select_all') }}"></th><th>{{ __('pms.roles.groups.'.$group) }}</th></tr></thead>
                    <tbody>
                    @foreach ($items as $p)
                        <tr><td><input type="checkbox" id="perm-{{ $p->id }}" name="permissions[]" value="{{ $p->id }}" @checked(in_array((int) $p->id, $checked, true))></td>
                            <td><label for="perm-{{ $p->id }}">{{ __('pms.roles.perm.'.str_replace('.', '_', $p->code)) }} <span class="text-muted text-[11px]">{{ $p->code }}</span></label></td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endforeach
        </section>
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.roles.index', ['secili' => $role->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
