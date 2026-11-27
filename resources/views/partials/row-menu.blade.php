{{-- Satır işlem menüsü: data-row-actions='{"card": url, …}' olan satıra tıklayınca / Enter ile açılır (kontrol paneli listeleri). --}}
<div id="row-menu" hidden class="fixed z-50 min-w-48 bg-panel border border-line rounded shadow-lg py-1 text-[13.5px]" role="menu">
    @foreach (['card' => 'Enter', 'room' => 'A', 'checkin' => 'G', 'folio' => 'B', 'checkout' => 'O', 'letter' => 'L', 'navigator' => 'N'] as $action => $key)
        <a data-menu-action="{{ $action }}" role="menuitem" href="#" class="flex items-center gap-3 px-3 py-1.5 hover:bg-brand-50 focus:bg-brand-50 outline-none">{{ __('pms.rowmenu.'.$action) }} <span class="kbd ml-auto">{{ $key }}</span></a>
    @endforeach
</div>
<form method="POST" id="row-menu-post" hidden>@csrf</form>
