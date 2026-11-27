{{-- Yazdırma sayfası (folyo, fatura, kasa raporu): A4, araç çubuğu ekranda görünür, baskıda gizlenir. Ctrl+P yazdır, Esc kapat. --}}
@php $property = app(\App\Domain\Core\Models\Property::class); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ $property->name }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; color: #111; font: 10pt/1.35 "Segoe UI", system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif; }
        .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm 12mm; background: #fff; }
        .bar { position: sticky; top: 0; z-index: 1; display: flex; gap: 6px; justify-content: center; padding: 8px; background: #2b2b2b; font: 13px system-ui, sans-serif; }
        .bar a, .bar button { padding: 4px 12px; border: 1px solid #777; border-radius: 3px; background: #444; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
        .bar kbd { margin-left: 6px; opacity: .7; font-size: 11px; }
        header.doc { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        header.doc h1 { margin: 0; font-size: 15pt; }
        header.doc .hotel { font-size: 9pt; color: #444; }
        header.doc .title { text-align: right; }
        header.doc .title b { display: block; font-size: 14pt; letter-spacing: .04em; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 12px; }
        .meta div { display: flex; gap: 8px; } .meta dt { min-width: 34mm; color: #555; } .meta dd { margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 4px; text-align: left; vertical-align: top; }
        thead th { border-bottom: 1px solid #111; font-size: 9pt; color: #333; }
        tbody td { border-bottom: 1px solid #ddd; }
        .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .totals { margin-top: 10px; margin-left: auto; width: 80mm; }
        .totals td { border: 0; padding: 2px 4px; } .totals tr.big td { border-top: 1px solid #111; font-weight: 700; font-size: 11pt; }
        .muted { color: #666; } .small { font-size: 8.5pt; }
        footer.doc { margin-top: 18px; padding-top: 6px; border-top: 1px solid #ccc; font-size: 8.5pt; color: #555; display: flex; justify-content: space-between; }
        .sign { margin-top: 28px; display: flex; justify-content: space-between; } .sign span { width: 60mm; border-top: 1px solid #111; padding-top: 3px; text-align: center; font-size: 9pt; }
        @media print { body { background: #fff; } .bar { display: none; } .page { width: auto; min-height: 0; margin: 0; padding: 0; } }
        @media (max-width: 820px) { .page { width: auto; min-height: 0; margin: 0; padding: 16px; } .meta { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<nav class="bar">
    <button type="button" onclick="window.print()" autofocus>{{ __('pms.res.print') }} <kbd>Ctrl+P</kbd></button>
    <a href="@yield('back')" data-close>{{ __('pms.common.close') }} <kbd>Esc</kbd></a>
</nav>
<main class="page">
    <header class="doc">
        <div><h1>{{ $property->legal_name ?: $property->name }}</h1>
            <div class="hotel">{{ $property->address }}@if ($property->city) · {{ $property->city }}@endif<br>
                @if ($property->tax_office || $property->tax_number){{ __('pms.prof.tax_office') }}: {{ $property->tax_office }} · {{ __('pms.prof.tax_number') }}: {{ $property->tax_number }}<br>@endif
                {{ $property->phone }}@if ($property->email) · {{ $property->email }}@endif</div></div>
        <div class="title">@yield('doc-title')</div>
    </header>
    @yield('content')
    <footer class="doc"><span>{{ $property->name }}@if ($property->website) · {{ $property->website }}@endif</span><span>{{ now()->format('d.m.Y H:i') }} · {{ auth()->user()?->username }}</span></footer>
</main>
<script>document.addEventListener('keydown', (e) => { if (e.key === 'Escape') location.href = document.querySelector('[data-close]').href; });</script>
</body>
</html>
