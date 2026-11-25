<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pms.login.title') }} — {{ __('pms.app') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex items-center justify-center min-h-full px-4 bg-brand-900">
    <div class="w-full max-w-sm flex flex-col gap-2">
        <form method="POST" class="flex justify-end gap-1 text-brand-100" aria-label="{{ __('pms.language') }}">
            @csrf
            @foreach (config('app.locales') as $code => $label)
                <button formaction="{{ route('locale', $code) }}" lang="{{ $code }}" @class(['px-2 rounded text-[12px]', 'bg-brand-600 text-white' => app()->getLocale() === $code])>{{ $label }}</button>
            @endforeach
        </form>
        <form method="POST" action="{{ url('/giris') }}" class="win">
            @csrf
            <div class="win-title">{{ __('pms.app') }} — {{ __('pms.login.title') }}</div>
            <div class="p-4 flex flex-col gap-3">
                <div>
                    <label class="field-label" for="login">{{ __('pms.login.username') }}</label>
                    <input id="login" name="login" value="{{ old('login') }}" class="input @error('login') input-error @enderror" autocomplete="username" autofocus required>
                    @error('login')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label" for="password">{{ __('pms.login.password') }}</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                </div>
                <label class="flex items-center gap-2 text-[12px] text-muted"><input type="checkbox" name="remember" value="1"> {{ __('pms.login.remember') }}</label>
                <button class="btn btn-primary justify-center">{{ __('pms.login.submit') }}</button>
            </div>
        </form>
    </div>
</body>
</html>
