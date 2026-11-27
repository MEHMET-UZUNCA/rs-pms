@php
    $user = auth()->user();
    $businessDate = $currentProperty->current_business_date;
    $can = fn (string $permission) => Gate::allows('permission', $permission);
    $menus = \App\Support\Menu::items();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('pms.app')) — {{ $currentProperty->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col h-full">
    <header class="flex items-center gap-4 px-3 h-9 bg-brand-900 text-white shrink-0">
        <a href="{{ route('home') }}" class="font-semibold tracking-wide">{{ __('pms.app') }}</a>
        <span class="text-brand-200">{{ $currentProperty->name }}</span>
        @if ($can('reservations.view') && (\App\Domain\Reservations\Support\ReservationOptions::get($currentProperty->id)['queue_rooms'] ?? false))
            @php $queue = app(\App\Domain\Reservations\Services\QueueService::class)->counts($currentProperty->id); @endphp
            @if ($queue['total'] > 0)
                <a href="{{ route('queue.index') }}" data-queue-badge @class(['px-2 rounded text-[12px]', 'bg-ok text-white animate-pulse' => $queue['ready'] > 0, 'bg-brand-700 text-brand-100' => ! $queue['ready']])>
                    {{ __('pms.queue.badge', ['total' => $queue['total'], 'ready' => $queue['ready']]) }}</a>
            @endif
        @endif
        @if ($can('front_office.requests') && ($lateRequests = app(\App\Domain\FrontOffice\Services\GuestRequestService::class)->overdueCount($currentProperty->id)) > 0)
            <a href="{{ route('requests.index', ['sekme' => 'overdue']) }}" data-requests-badge class="px-2 rounded text-[12px] bg-danger text-white">{{ __('pms.req.badge', ['count' => $lateRequests]) }}</a>
        @endif
        <div class="ml-auto flex items-center gap-3 text-brand-100">
            <form method="POST" class="flex items-center gap-1" aria-label="{{ __('pms.language') }}">
                @csrf
                @foreach (config('app.locales') as $code => $label)
                    <button formaction="{{ route('locale', $code) }}" title="{{ $label }}" lang="{{ $code }}"
                        @class(['px-1.5 rounded text-[12px] uppercase', 'bg-brand-600 text-white' => app()->getLocale() === $code, 'hover:text-white' => app()->getLocale() !== $code])>{{ $code }}</button>
                @endforeach
            </form>
            <a href="{{ route('password.edit') }}" class="hover:text-white" title="{{ __('pms.password.menu') }}">{{ $user->name }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="hover:text-white underline-offset-2 hover:underline">{{ __('pms.common.logout') }}</button></form>
        </div>
    </header>

    <nav class="relative z-40 flex flex-wrap items-center gap-0.5 px-2 min-h-8 bg-panel border-b border-line shrink-0" aria-label="Menu">
        @foreach ($menus as $menu => $items)
            <div class="menu-root relative">
                <div class="menu-item whitespace-nowrap" role="button" tabindex="0">{{ __('pms.menu.'.$menu) }}</div>
                <div class="menu-drop">
                    @foreach ($items as $item)
                        @php [$label, $url] = $item; $keys = $item[2] ?? null; @endphp
                        @if ($url)<a href="{{ $url }}" class="flex gap-6">{{ __('pms.menu.'.$label) }}@if ($keys)<span class="ml-auto text-muted text-[12px]">{{ $keys }}</span>@endif</a>
                        @else<span title="{{ __('pms.menu.coming_soon') }}" class="flex gap-6">{{ __('pms.menu.'.$label) }}@if ($keys)<span class="ml-auto text-[12px]">{{ $keys }}</span>@endif</span>@endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- Fidelio genel kısayolları: her ekrandan çalışır --}}
    @foreach (\App\Support\QuickKeys::global() as $quick)
        @if ($quick['url'])<a href="{{ $quick['url'] }}" data-hotkey="{{ $quick['hotkey'] }}" data-hotkey-global hidden aria-hidden="true" tabindex="-1"></a>@endif
    @endforeach
    <a href="{{ route('shortcuts') }}" data-hotkey="f1" data-hotkey-global hidden aria-hidden="true" tabindex="-1"></a>

    <main class="flex-1 min-h-0 p-3 flex flex-col gap-2">
        @if (session('status'))<div class="flash flash-ok" role="status">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="flash flash-error" role="alert">{{ session('error') }}</div>@endif
        @yield('content')
    </main>

    <footer class="flex items-center gap-4 px-3 h-7 bg-panel border-t border-line text-[12px] text-muted shrink-0">
        <span>{{ __('pms.common.property') }}: <b class="text-ink font-semibold">{{ $currentProperty->code }}</b></span>
        <span>{{ __('pms.common.user') }}: <b class="text-ink font-semibold">{{ $user->username ?? $user->email }}</b></span>
        <span class="ml-auto">{{ __('pms.common.business_date') }}</span>
        <span class="px-2 py-0.5 rounded bg-brand-700 text-white font-semibold" data-business-date>{{ $businessDate?->format('d.m.Y') ?? '—' }}</span>
        <span data-clock data-locale="{{ app()->getLocale() }}"></span>
    </footer>
</body>
</html>
