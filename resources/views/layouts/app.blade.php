@php
    $user = auth()->user();
    $businessDate = $currentProperty->current_business_date;
    $can = fn (string $permission) => Gate::allows('permission', $permission);
    // Fidelio Suite8 ana menüleri (docs/07-arayuz.md §7.1). Hazır olan ekranlar bağlantı, diğerleri sırası gelince açılır.
    $menus = [
        'reservation' => [['reservation_navigator', $can('reservations.view') ? route('reservations.navigator') : null], ['quick_reservation', null], ['availability', null], ['rate_query', null], ['block_reservation', null]],
        'front_office' => [['arrivals', $can('reservations.view') ? route('reservations.navigator', ['f' => 'gelecek']) : null], ['in_house', $can('reservations.view') ? route('reservations.navigator', ['f' => 'iceride']) : null], ['room_rack', $can('reservations.view') ? route('front-office.room-rack') : null], ['house_status', null]],
        'cashiering' => [['billing', $can('cashiering.billing') ? route('reservations.navigator', ['f' => 'iceride']) : null], ['posting', null], ['passer_by', null], ['close_cashier', null], ['accounts_receivable', null]],
        'housekeeping' => [['room_status', null], ['attendants', null], ['discrepant_rooms', null]],
        'profiles' => [['profile_navigator', null]],
        'conference' => [['conference_diary', null], ['events', null]],
        'miscellaneous' => [['night_audit', $can('night_audit.view') ? route('night-audit.index') : null], ['logbook', null], ['reports', null]],
        'setup' => [['outlets', $can('setup.outlets') ? route('setup.outlets.index') : null], ['department_codes', $can('setup.transaction_codes') ? route('setup.transaction-codes.index') : null], ['taxes', null], ['users', null]],
    ];
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

    <nav class="flex items-center gap-0.5 px-2 h-8 bg-panel border-b border-line shrink-0 overflow-x-auto" aria-label="Menu">
        @foreach ($menus as $menu => $items)
            <div class="menu-root relative">
                <div class="menu-item whitespace-nowrap" role="button" tabindex="0">{{ __('pms.menu.'.$menu) }}</div>
                <div class="menu-drop">
                    @foreach ($items as [$label, $url])
                        @if ($url)<a href="{{ $url }}">{{ __('pms.menu.'.$label) }}</a>@else<span title="{{ __('pms.menu.coming_soon') }}">{{ __('pms.menu.'.$label) }}</span>@endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- Fidelio genel kısayolları: her ekrandan çalışır --}}
    @if ($can('reservations.view'))
        <a href="{{ route('front-office.room-rack') }}" data-hotkey="ctrl+l" data-hotkey-global hidden aria-hidden="true" tabindex="-1"></a>
    @endif

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
