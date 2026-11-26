@extends('layouts.app')
@section('title', __('pms.common.home'))
@section('content')
<div class="win flex-1">
    <div class="win-title">{{ __('pms.common.home') }}</div>
    <div class="p-4 text-muted">
        <p>{{ __('pms.common.available_screens') }}</p>
        <ul class="list-disc ml-5 mt-2 text-ink" data-available-screens>
            @foreach (\App\Support\Menu::items() as $menu => $items)
                @foreach ($items as $item)
                    @if ($item[1] && $menu !== 'quick_keys')<li><a class="text-brand-600 hover:underline" href="{{ $item[1] }}">{{ __('pms.menu.'.$menu) }} → {{ __('pms.menu.'.$item[0]) }}</a></li>@endif
                @endforeach
            @endforeach
        </ul>
    </div>
</div>
@endsection
