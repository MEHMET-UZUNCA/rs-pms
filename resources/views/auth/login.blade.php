{{-- Giriş ekranı: tam ekran arka plan (Ayarlar → Giriş ekranı'ndan görsel, karartma, karşılama metni), yarı saydam giriş kartı. --}}
@php
    $bg = $look['image'] ? route('login.background', ['v' => $look['image_version']]) : null;
    $overlay = max(0, min(85, (int) $look['overlay'])) / 100;
    $hotelName = $hotel?->name ?? __('pms.app');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14284b">
    <title>{{ __('pms.login.title') }} — {{ $hotelName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-bg { background: radial-gradient(1200px 600px at 15% 20%, rgba(56, 120, 200, .35), transparent 60%),
                    radial-gradient(900px 500px at 85% 80%, rgba(20, 160, 150, .25), transparent 60%),
                    linear-gradient(135deg, #0c1b33 0%, #14284b 45%, #0e3b4a 100%); }
        .login-card { background: rgba(255, 255, 255, .96); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
        .login-field { position: relative; }
        .login-field svg { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); width: 1.05rem; height: 1.05rem; color: #64748b; pointer-events: none; }
        .login-field .input { height: 2.75rem; padding-left: 2.4rem; font-size: 15px; border-radius: .6rem; }
        @media (prefers-reduced-motion: no-preference) { .login-card { animation: login-in .35s ease-out; } }
        @keyframes login-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="min-h-full">
<div class="relative min-h-screen flex login-bg" data-login-screen>
    @if ($bg)
        <img src="{{ $bg }}" alt="" class="absolute inset-0 w-full h-full object-cover" data-login-background>
        <div class="absolute inset-0" style="background: linear-gradient(90deg, rgba(8,18,36,{{ min(.95, $overlay + .2) }}) 0%, rgba(8,18,36,{{ $overlay }}) 55%, rgba(8,18,36,{{ max(0, $overlay - .1) }}) 100%)"></div>
    @endif

    <div class="relative z-10 w-full grid lg:grid-cols-[1fr_28rem] gap-8 px-5 sm:px-10 py-8">
        {{-- Sol: otel adı, karşılama, saat (geniş ekranda) --}}
        <section class="hidden lg:flex flex-col justify-between text-white py-6">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 font-bold tracking-wide">RS</span>
                <span class="text-[15px] tracking-[.2em] uppercase text-white/80">{{ __('pms.app') }}</span>
            </div>
            <div class="max-w-xl">
                <h1 class="text-5xl font-semibold leading-tight drop-shadow">{{ $look['title'] ?: $hotelName }}</h1>
                <p class="mt-4 text-lg text-white/85 drop-shadow">{{ $look['subtitle'] ?: __('pms.loginscreen.default_subtitle') }}</p>
            </div>
            <div class="text-white/85">
                <div class="text-4xl font-light tabular-nums" data-login-clock>{{ now()->format('H:i') }}</div>
                <div class="text-[14px] mt-1" data-login-date>{{ now()->translatedFormat('l, d F Y') }}</div>
            </div>
        </section>

        {{-- Sağ: giriş kartı --}}
        <section class="flex items-center justify-center lg:justify-end">
            <div class="w-full max-w-md">
                <div class="lg:hidden text-center text-white mb-5">
                    <div class="text-[13px] tracking-[.2em] uppercase text-white/75">{{ __('pms.app') }}</div>
                    <div class="text-2xl font-semibold mt-1 drop-shadow">{{ $look['title'] ?: $hotelName }}</div>
                </div>
                <form method="POST" action="{{ url('/giris') }}" class="login-card rounded-2xl shadow-2xl ring-1 ring-white/40 p-7 sm:p-8 flex flex-col gap-4">
                    @csrf
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-[22px] font-semibold text-ink">{{ __('pms.loginscreen.welcome') }}</h2>
                            <p class="text-[13.5px] text-muted mt-0.5">{{ __('pms.loginscreen.sign_in_hint') }}</p>
                        </div>
                        <div class="flex rounded-lg bg-surface p-0.5 text-[12px]" role="group" aria-label="{{ __('pms.language') }}">
                            @foreach (config('app.locales') as $code => $label)
                                <button form="lang-form" formaction="{{ route('locale', $code) }}" lang="{{ $code }}" @class(['px-2 py-1 rounded-md', 'bg-white shadow text-ink font-semibold' => app()->getLocale() === $code, 'text-muted' => app()->getLocale() !== $code])>{{ strtoupper($code) }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-lg bg-danger/10 text-danger text-[13px] px-3 py-2" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <div>
                        <label class="field-label" for="login">{{ __('pms.login.username') }}</label>
                        <div class="login-field">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                            <input id="login" name="login" value="{{ old('login') }}" class="input w-full @error('login') input-error @enderror" autocomplete="username" autocapitalize="characters" autofocus required>
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="password">{{ __('pms.login.password') }}</label>
                        <div class="login-field">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            <input id="password" name="password" type="password" class="input w-full !pr-16" autocomplete="current-password" required data-password>
                            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] text-brand-600 px-2 py-1 rounded hover:bg-brand-50" data-toggle-password
                                    data-show="{{ __('pms.loginscreen.show') }}" data-hide="{{ __('pms.loginscreen.hide') }}">{{ __('pms.loginscreen.show') }}</button>
                        </div>
                        <p class="text-[12px] text-amber-700 mt-1" data-caps-warning hidden>{{ __('pms.loginscreen.caps_lock') }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-muted"><input type="checkbox" name="remember" value="1"> {{ __('pms.login.remember') }}</label>
                    <button class="btn btn-primary justify-center !h-11 !text-[15px] !rounded-lg">{{ __('pms.login.submit') }}</button>
                </form>
                <form method="POST" id="lang-form" hidden>@csrf</form>{{-- dil düğmeleri bu forma bağlı: giriş formunda Enter girişi yapar, dili değiştirmez --}}
                <p class="text-center text-[12px] text-white/70 mt-4">{{ $hotelName }} · {{ __('pms.app') }}</p>
            </div>
        </section>
    </div>
</div>
<script>
    (() => {
        const pw = document.querySelector('[data-password]');
        const toggle = document.querySelector('[data-toggle-password]');
        toggle?.addEventListener('click', () => {
            const show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.textContent = show ? toggle.dataset.hide : toggle.dataset.show;
            pw.focus();
        });
        const caps = document.querySelector('[data-caps-warning]');
        pw?.addEventListener('keyup', (e) => { if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock'); });
        const clock = document.querySelector('[data-login-clock]');
        const tick = () => { clock.textContent = new Date().toLocaleTimeString('{{ app()->getLocale() }}', { hour: '2-digit', minute: '2-digit', hour12: false }); };
        if (clock) { tick(); setInterval(tick, 15000); }
    })();
</script>
</body>
</html>
