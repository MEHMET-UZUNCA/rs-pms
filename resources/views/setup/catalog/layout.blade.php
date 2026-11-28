{{-- Ayar tanım ekranları için ortak çerçeve: solda ayar ağacı, üstte başlık ve hatalar. --}}
@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ $title }}</span>@isset($subtitle)<span class="font-normal text-brand-100 text-[12px]">{{ $subtitle }}</span>@endisset</div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-lg:hidden">@include('setup._tree')</aside>
        <div class="flex-1 min-w-0 overflow-auto p-3">
            @if ($errors->any())<div class="flash flash-error mb-2" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            @yield('catalog')
        </div>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
