@extends('layouts.app')
@section('title', __('pms.keys.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.keys.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.keys.subtitle') }}</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <section class="win !min-h-fit p-3 self-start">
            <b>{{ __('pms.menu.quick_keys') }}</b>
            <p class="text-[12px] text-muted mt-0.5">{{ __('pms.keys.global_hint') }}</p>
            <table class="grid-table mt-2" data-quick-keys>
                <tbody>
                @foreach (\App\Support\QuickKeys::global() as $k)
                    <tr @class(['text-muted' => ! $k['url']])>
                        <td>@if ($k['url'])<a href="{{ $k['url'] }}" class="text-brand-600 hover:underline">{{ __('pms.menu.'.$k['label']) }}</a>@else{{ __('pms.menu.'.$k['label']) }} <span class="text-[11px]">({{ __('pms.menu.coming_soon') }})</span>@endif</td>
                        <td class="text-right"><span class="kbd">{{ $k['display'] }}</span></td>
                    </tr>
                @endforeach
                    <tr><td>{{ __('pms.menu.all_shortcuts') }}</td><td class="text-right"><span class="kbd">F1</span></td></tr>
                </tbody>
            </table>
            <p class="text-[12px] text-muted mt-2">{{ __('pms.keys.browser_note') }}</p>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 content-start">
            @foreach (\App\Support\QuickKeys::screens() as $screen => $keys)
                <div class="win !min-h-fit p-3">
                    <b>{{ __($screen) }}</b>
                    <table class="grid-table mt-1"><tbody>
                        @foreach ($keys as [$key, $label])
                            <tr><td>{{ __($label) }}</td><td class="text-right whitespace-nowrap"><span class="kbd">{{ $key }}</span></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @endforeach
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</div>
@endsection
