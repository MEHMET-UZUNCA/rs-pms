@extends('layouts.app')
@section('title', $user->exists ? __('pms.users.edit_title', ['name' => $user->username]) : __('pms.users.new_title'))
@section('content')
<form method="POST" action="{{ $user->exists ? route('setup.users.update', $user) : route('setup.users.store') }}" class="win flex-1">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $user->exists ? __('pms.users.edit_title', ['name' => $user->username]) : __('pms.users.new_title') }}</span></div>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="p-3 grid gap-2.5 max-w-xl">
        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="field-label" for="username">{{ __('pms.users.username') }} *</label>
                <input id="username" name="username" value="{{ old('username', $user->username) }}" maxlength="30" class="input uppercase @error('username') input-error @enderror" required autofocus>
            </div>
            <div class="col-span-2">
                <label class="field-label" for="name">{{ __('pms.common.name') }} *</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="150" class="input @error('name') input-error @enderror" required>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="field-label" for="initials">{{ __('pms.users.initials') }}</label>
                <input id="initials" name="initials" value="{{ old('initials', $user->initials) }}" maxlength="5" class="input uppercase">
            </div>
            <div class="col-span-2">
                <label class="field-label" for="title">{{ __('pms.users.title_field') }}</label>
                <input id="title" name="title" value="{{ old('title', $user->title) }}" maxlength="60" class="input">
            </div>
        </div>
        <div>
            <label class="field-label" for="email">{{ __('pms.users.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', str_ends_with((string) $user->email, '@rocks.local') ? '' : $user->email) }}" maxlength="150" class="input @error('email') input-error @enderror">
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
                <label class="field-label" for="role_id">{{ __('pms.users.role') }} *</label>
                <select id="role_id" name="role_id" class="input @error('role_id') input-error @enderror" required>
                    <option value=""></option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((int) old('role_id', $roleId) === (int) $role->id)>{{ $role->code }} — {{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="locale">{{ __('pms.language') }}</label>
                <select id="locale" name="locale" class="input">
                    @foreach (config('app.locales') as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> {{ __('pms.common.active') }}</label>
        @unless ($user->exists)<p class="text-[12px] text-muted">{{ __('pms.users.new_hint') }}</p>@endunless
    </section>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line mt-auto">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.users.index', ['secili' => $user->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
