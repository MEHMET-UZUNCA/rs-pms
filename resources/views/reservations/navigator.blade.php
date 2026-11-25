@extends('layouts.app')
@section('title', 'Rezervasyon Navigatörü')
@section('content')
@php
    $badge = fn (string $s) => match ($s) {
        'in_house' => 'bg-ok/15 text-ok', 'confirmed' => 'bg-brand-100 text-brand-700', 'tentative', 'waitlist' => 'bg-amber-100 text-amber-800',
        'cancelled', 'no_show' => 'bg-danger/10 text-danger', default => 'bg-surface text-muted',
    };
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="win flex-1">
    <div class="win-title"><span>Rezervasyon Navigatörü</span><span class="font-normal text-brand-100 text-[12px]">İş günü {{ $businessDate->format('d.m.Y') }}</span></div>

    <form method="GET" action="{{ route('reservations.navigator') }}" class="grid gap-2 px-3 py-2 border-b border-line grid-cols-2 md:grid-cols-4 xl:grid-cols-8 items-end" data-navigator-query>
        <label class="xl:col-span-2"><span class="field-label">Ad <span class="kbd">Alt+A</span></span>
            <input name="ad" value="{{ $query['ad'] }}" class="input" autocomplete="off" data-hotkey="alt+a" @if (! $filter) autofocus @endif></label>
        <label><span class="field-label">Oda</span><input name="oda" value="{{ $query['oda'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">Rez. no</span><input name="no" value="{{ $query['no'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">Şirket / Acente</span><input name="firma" value="{{ $query['firma'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">Geliş <span class="text-muted">(t, +1, 25.11)</span></span><input name="gelis" value="{{ $query['gelis'] }}" class="input" autocomplete="off"></label>
        <label><span class="field-label">Çıkış</span><input name="cikis" value="{{ $query['cikis'] }}" class="input" autocomplete="off"></label>
        <div class="flex gap-2">
            <select name="durum" class="input min-w-36" aria-label="Durum">
                <option value="">Tüm durumlar</option>
                @foreach (\App\Http\Controllers\Reservations\NavigatorController::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($query['durum'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" data-hotkey="f3" data-search-button>Ara <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
        </div>
    </form>

    <nav class="flex flex-wrap gap-1.5 px-3 py-2 border-b border-line" aria-label="Hızlı filtreler">
        @foreach (\App\Http\Controllers\Reservations\NavigatorController::FILTERS as $key => [$label, $hotkey])
            <a href="{{ route('reservations.navigator', ['f' => $key]) }}" data-hotkey="{{ $hotkey }}"
               @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full border text-[13px] whitespace-nowrap',
                   'bg-brand-600 border-brand-600 text-white' => $filter === $key, 'bg-white border-line hover:bg-brand-50' => $filter !== $key])>
                {{ $label }} <span class="text-[11px] opacity-70">{{ ucfirst(str_replace('alt+', 'Alt+', $hotkey)) }}</span>
            </a>
        @endforeach
    </nav>

    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-auto">
                @if ($isFa)
                    <table class="grid-table" id="res-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#res-detail">
                        <thead><tr><th>Hesap no</th><th>Ad</th><th>Tip</th><th>Geçerlilik</th><th>Durum</th><th class="text-right">Bakiye</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $folio)
                            <tr data-id="{{ $folio->id }}" data-url-detail="{{ route('reservations.folio-summary', $folio) }}">
                                <td class="font-semibold">{{ $folio->folio_number }}</td><td>{{ $folio->name }}</td><td>{{ $folio->type }}</td>
                                <td>{{ $folio->valid_from?->format('d.m.Y') }} – {{ $folio->valid_until?->format('d.m.Y') }}</td>
                                <td>{{ $folio->status === 'open' ? 'Açık' : 'Kapalı' }}</td>
                                <td class="text-right tabular-nums">{{ $money($folio->balance) }}</td>
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="6" class="text-center text-muted py-6">Kayıt yok.</td></tr>
                        </tbody>
                    </table>
                @else
                    <table class="grid-table" id="res-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#res-detail">
                        <thead><tr>
                            <th>Oda</th><th>Misafir</th><th>Geliş</th><th>Çıkış</th><th class="text-right">Gece</th><th>Tip</th><th>Fiyat kodu</th>
                            <th>Kişi</th><th>Acente / Şirket</th><th>Durum</th><th class="text-right">Bakiye</th><th>Rez. no</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr data-id="{{ $r->id }}" data-url-detail="{{ route('reservations.summary', $r) }}"
                                @if ($r->status !== 'in_house' && $r->arrival_date?->isSameDay($businessDate) && $r->room_id) data-url-checkin="{{ route('reservations.check-in', $r) }}" @endif
                                @class(['is-inactive' => in_array($r->status, ['cancelled', 'no_show'], true)])>
                                <td class="font-semibold">{{ $r->room?->number }}</td>
                                <td>{{ $r->profile?->display_name }}</td>
                                <td>{{ $r->arrival_date?->format('d.m.Y') }}</td><td>{{ $r->departure_date?->format('d.m.Y') }}</td>
                                <td class="text-right">{{ $r->nights }}</td>
                                <td>{{ $r->roomType?->code }}</td><td>{{ $r->rateCode?->code }}</td>
                                <td>{{ $r->adults }}@if ($r->children)+{{ $r->children }}@endif</td>
                                <td>{{ $r->agent_name ?? $r->company_name }}</td>
                                <td><span class="px-2 py-0.5 rounded-full text-[12px] {{ $badge($r->status) }}">{{ \App\Http\Controllers\Reservations\NavigatorController::STATUSES[$r->status] ?? $r->status }}</span></td>
                                <td class="text-right tabular-nums">{{ $money($r->balance) }}</td>
                                <td class="text-muted">{{ $r->confirmation_number }}</td>
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="12" class="text-center text-muted py-6">Kayıt yok.</td></tr>
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="px-3 py-1 border-t border-line text-[12px] text-muted flex gap-4">
                <span>{{ $rows->count() }} kayıt @if ($rows->count() >= $limit)(ilk {{ $limit }} — sorguyu daraltın)@endif</span>
                <span class="ml-auto">↑/↓ gez · F3 ara · Alt+1…0 filtre</span>
            </div>
            <div id="res-detail" class="border-t border-line min-h-44 max-h-[40%] overflow-auto bg-surface" data-empty='<p class="p-3 text-muted">Bir satır seçin.</p>'>
                <p class="p-3 text-muted">Bir satır seçin.</p>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <span class="btn btn-side opacity-40" title="Rezervasyon kartı sırası gelince">Yeni <span class="kbd ml-auto">Ctrl+N</span></span>
            <span class="btn btn-side opacity-40" title="Rezervasyon kartı sırası gelince">Düzenle <span class="kbd ml-auto">Enter</span></span>
            @can('permission', 'reservations.checkin')
                <form method="POST" data-confirm="Seçili rezervasyonun girişi yapılsın mı?">
                    @csrf
                    <button class="btn btn-side" data-needs-selection="res-grid" data-action="checkin" data-hotkey="alt+g">Giriş <span class="kbd ml-auto">Alt+G</span></button>
                </form>
            @endcan
            <span class="btn btn-side opacity-40" title="Sırası gelince">Folyo</span>
            <span class="btn btn-side opacity-40" title="Sırası gelince">İptal</span>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">Kapat <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
