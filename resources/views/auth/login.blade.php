<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — RS PMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex items-center justify-center min-h-full px-4 bg-brand-900">
    <form method="POST" action="{{ url('/giris') }}" class="win w-full max-w-sm">
        @csrf
        <div class="win-title">RS PMS — Giriş</div>
        <div class="p-4 flex flex-col gap-3">
            <div>
                <label class="field-label" for="login">Kullanıcı adı</label>
                <input id="login" name="login" value="{{ old('login') }}" class="input @error('login') input-error @enderror" autocomplete="username" autofocus required>
                @error('login')<p class="text-danger text-[12px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="password">Şifre</label>
                <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
            </div>
            <label class="flex items-center gap-2 text-[12px] text-muted"><input type="checkbox" name="remember" value="1"> Beni hatırla</label>
            <button class="btn btn-primary justify-center">Giriş</button>
        </div>
    </form>
</body>
</html>
