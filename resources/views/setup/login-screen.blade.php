@extends('layouts.app')
@section('title', __('pms.loginscreen.title'))
@section('content')
<form method="POST" action="{{ route('setup.login-screen.update') }}" enctype="multipart/form-data" class="win flex-1" data-login-screen-form>
    @csrf
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.loginscreen.title') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-lg:hidden">@include('setup._tree')</aside>
        <div class="flex-1 min-w-0 overflow-auto p-4">
            @if ($errors->any())<div class="flash flash-error mb-3" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            <div class="grid gap-6 xl:grid-cols-[minmax(0,26rem)_1fr] max-w-6xl">
                <div class="flex flex-col gap-4">
                    <div>
                        <label class="field-label" for="image">{{ __('pms.loginscreen.image') }}</label>
                        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-[13px]" data-bg-input>
                        <p class="text-[12px] text-muted mt-1">{{ __('pms.loginscreen.image_hint') }}</p>
                        @if ($a['image'])<label class="flex items-center gap-2 mt-2 text-[13px]"><input type="checkbox" name="remove_image" value="1"> {{ __('pms.loginscreen.remove') }}</label>@endif
                    </div>
                    <div>
                        <label class="field-label" for="overlay">{{ __('pms.loginscreen.overlay') }} <b data-overlay-value>{{ old('overlay', $a['overlay']) }}%</b></label>
                        <input id="overlay" name="overlay" type="range" min="0" max="85" step="5" value="{{ old('overlay', $a['overlay']) }}" class="w-full" data-overlay>
                        <p class="text-[12px] text-muted">{{ __('pms.loginscreen.overlay_hint') }}</p>
                    </div>
                    <div><label class="field-label" for="title">{{ __('pms.loginscreen.welcome_title') }}</label>
                        <input id="title" name="title" value="{{ old('title', $a['title']) }}" maxlength="80" class="input" placeholder="{{ $currentProperty->name }}" data-title></div>
                    <div><label class="field-label" for="subtitle">{{ __('pms.loginscreen.welcome_text') }}</label>
                        <textarea id="subtitle" name="subtitle" rows="2" maxlength="200" class="input !h-auto py-1" placeholder="{{ __('pms.loginscreen.default_subtitle') }}" data-subtitle>{{ old('subtitle', $a['subtitle']) }}</textarea></div>
                </div>
                {{-- Önizleme --}}
                <div>
                    <div class="field-label">{{ __('pms.loginscreen.preview') }}</div>
                    <div class="relative aspect-video rounded-xl overflow-hidden shadow ring-1 ring-line" style="background: linear-gradient(135deg, #0c1b33 0%, #14284b 45%, #0e3b4a 100%)" data-preview>
                        <img src="{{ $a['image'] ? route('login.background', ['v' => $a['image_version']]) : '' }}" alt="" class="absolute inset-0 w-full h-full object-cover" @unless ($a['image']) hidden @endunless data-preview-img>
                        <div class="absolute inset-0" style="background: rgba(8,18,36,{{ $a['overlay'] / 100 }})" data-preview-overlay></div>
                        <div class="absolute left-[6%] top-1/2 -translate-y-1/2 text-white max-w-[45%]">
                            <div class="text-[20px] font-semibold leading-tight" data-preview-title>{{ $a['title'] ?: $currentProperty->name }}</div>
                            <div class="text-[12px] mt-1 text-white/85" data-preview-subtitle>{{ $a['subtitle'] ?: __('pms.loginscreen.default_subtitle') }}</div>
                        </div>
                        <div class="absolute right-[6%] top-1/2 -translate-y-1/2 w-[32%] rounded-lg bg-white/90 p-3 flex flex-col gap-1.5">
                            <div class="h-2.5 w-1/2 rounded bg-ink/70"></div><div class="h-5 rounded bg-surface ring-1 ring-line"></div><div class="h-5 rounded bg-surface ring-1 ring-line"></div><div class="h-5 rounded bg-brand-600"></div>
                        </div>
                    </div>
                    <p class="text-[12px] text-muted mt-2"><a class="text-brand-600 hover:underline" href="{{ route('login') }}" target="_blank">{{ __('pms.loginscreen.open') }}</a> · {{ __('pms.loginscreen.open_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.common.save') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('home') }}" class="btn" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
<script>
    (() => {
        const q = (s) => document.querySelector(s);
        q('[data-overlay]')?.addEventListener('input', (e) => { q('[data-overlay-value]').textContent = e.target.value + '%'; q('[data-preview-overlay]').style.background = `rgba(8,18,36,${e.target.value / 100})`; });
        q('[data-title]')?.addEventListener('input', (e) => { q('[data-preview-title]').textContent = e.target.value || e.target.placeholder; });
        q('[data-subtitle]')?.addEventListener('input', (e) => { q('[data-preview-subtitle]').textContent = e.target.value || e.target.placeholder; });
        q('[data-bg-input]')?.addEventListener('change', (e) => { const f = e.target.files[0]; if (!f) return; const img = q('[data-preview-img]'); img.src = URL.createObjectURL(f); img.hidden = false; });
    })();
</script>
@endsection
