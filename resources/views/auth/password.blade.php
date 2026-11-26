<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pms.password.title') }} — {{ __('pms.app') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex items-center justify-center min-h-full px-4 bg-brand-900">
    <form method="POST" action="{{ route('password.update') }}" class="win w-full max-w-sm">
        @csrf @method('PUT')
        <div class="win-title">{{ __('pms.password.title') }} — {{ auth()->user()->username }}</div>
        <div class="p-4 flex flex-col gap-3">
            @if ($forced)<p class="flash flash-error">{{ __('pms.password.forced') }}</p>@endif
            @if ($errors->any())<div class="flash flash-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            <label><span class="field-label">{{ __('pms.password.current') }}</span><input type="password" name="current_password" class="input" autocomplete="current-password" required autofocus></label>
            <label><span class="field-label">{{ __('pms.password.new') }}</span><input type="password" name="password" class="input" autocomplete="new-password" required></label>
            <label><span class="field-label">{{ __('pms.password.confirm') }}</span><input type="password" name="password_confirmation" class="input" autocomplete="new-password" required></label>
            <p class="text-[12px] text-muted">{{ __('pms.password.rules') }}</p>
            <button class="btn btn-primary justify-center">{{ __('pms.password.save') }}</button>
        </div>
    </form>
</body>
</html>
