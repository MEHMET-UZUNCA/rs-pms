@php
    $user = auth()->user();
    $businessDate = $currentProperty->current_business_date;
    // Fidelio ana menüleri (docs/07-arayuz.md §7.1). Hazır olan ekranlar bağlantı, diğerleri sırası gelince açılır.
    $menus = [
        'Rezervasyon' => [['Rezervasyon Navigatörü', null], ['Hızlı Rezervasyon', null], ['Müsaitlik', null], ['Fiyat Sorgulama', null], ['Blok / Allotman', null]],
        'Ön Büro' => [['Gelecekler', null], ['İçeridekiler', null], ['Oda Planı', null], ['Ev Durumu', null]],
        'Kasa' => [['Folyo', null], ['Kayıt At', null], ['Ayakta Satış', null], ['Kasa Kapanışı', null], ['Cari Hesaplar', null]],
        'Kat Hizmetleri' => [['Oda Durumu', null], ['Görev Dağıtımı', null], ['Uyuşmazlıklar', null]],
        'Müşteri' => [['Profil Navigatörü', null]],
        'Toplantı/Banket' => [['Konferans Günlüğü', null], ['Etkinlikler', null]],
        'Çeşitli' => [['Gece Kapanışı', null], ['Vardiya Defteri', null], ['Raporlar', null]],
        'Ayarlar' => [['Outlet\'ler', Gate::allows('permission', 'setup.outlets') ? route('setup.outlets.index') : null], ['Departman Kodları', Gate::allows('permission', 'setup.transaction_codes') ? route('setup.transaction-codes.index') : null], ['Vergiler', null], ['Kullanıcılar', null]],
    ];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RS PMS') — {{ $currentProperty->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col h-full">
    <header class="flex items-center gap-4 px-3 h-9 bg-brand-900 text-white shrink-0">
        <a href="{{ route('home') }}" class="font-semibold tracking-wide">RS PMS</a>
        <span class="text-brand-200">{{ $currentProperty->name }}</span>
        <div class="ml-auto flex items-center gap-3 text-brand-100">
            <span>{{ $user->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="hover:text-white underline-offset-2 hover:underline">Çıkış</button></form>
        </div>
    </header>

    <nav class="flex items-center gap-0.5 px-2 h-8 bg-panel border-b border-line shrink-0" aria-label="Ana menü">
        @foreach ($menus as $menu => $items)
            <div class="menu-root relative">
                <div class="menu-item" role="button" tabindex="0">{{ $menu }}</div>
                <div class="menu-drop">
                    @foreach ($items as [$label, $url])
                        @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else<span title="Sırası gelince açılacak">{{ $label }}</span>@endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <main class="flex-1 min-h-0 p-3 flex flex-col gap-2">
        @if (session('status'))<div class="flash flash-ok" role="status">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="flash flash-error" role="alert">{{ session('error') }}</div>@endif
        @yield('content')
    </main>

    <footer class="flex items-center gap-4 px-3 h-7 bg-panel border-t border-line text-[12px] text-muted shrink-0">
        <span>Tesis: <b class="text-ink font-semibold">{{ $currentProperty->code }}</b></span>
        <span>Kullanıcı: <b class="text-ink font-semibold">{{ $user->username ?? $user->email }}</b></span>
        <span class="ml-auto">İş günü</span>
        <span class="px-2 py-0.5 rounded bg-brand-700 text-white font-semibold" data-business-date>{{ $businessDate?->format('d.m.Y') ?? '—' }}</span>
        <span data-clock></span>
    </footer>
</body>
</html>
