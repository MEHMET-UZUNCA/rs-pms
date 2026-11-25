{{-- Fidelio ayar ağacı (Setup → Configuration). Hazır olan ekranlar bağlantıdır. --}}
<nav class="tree text-[13px]" aria-label="Ayarlar">
    <div class="px-2 pt-1 pb-0.5 text-[11px] uppercase tracking-wide text-muted">Kasa</div>
    <a href="{{ route('setup.outlets.index') }}" @class(['is-current' => request()->routeIs('setup.outlets.*')])>Outlet'ler</a>
    <a href="{{ route('setup.transaction-codes.index') }}" @class(['is-current' => request()->routeIs('setup.transaction-codes.*')])>Departman kodları</a>
    <span class="is-soon">Departman kodu grupları</span>
    <span class="is-soon">Vergiler</span>
    <span class="is-soon">Ödeme tipleri</span>
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">Rezervasyon</div>
    <span class="is-soon">Fiyat kodları</span>
    <span class="is-soon">Oda tipleri</span>
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">Sistem</div>
    <span class="is-soon">Kullanıcılar ve roller</span>
</nav>
