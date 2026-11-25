@extends('layouts.app')
@section('title', "Outlet'ler")
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>Ayarlar — Outlet'ler</span><span class="font-normal text-brand-100 text-[12px]">Simphony gelir merkezleri (RVC) ve RS ONE departmanları</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.outlets.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">Hızlı arama
                    <input name="q" value="{{ $search }}" class="input w-56" data-grid-filter="outlet-grid" placeholder="Kod ya da ad" autocomplete="off" autofocus>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> Pasifleri göster</label>
                <span class="ml-auto text-muted text-[12px]">{{ $outlets->count() }} outlet</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="outlet-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th class="w-10">Sıra</th><th>Kod</th><th>Ad</th><th>Tip</th><th>Kaynak</th><th class="text-right">Simphony RVC</th>
                        <th>Varsayılan kod</th><th class="text-right">Kod sayısı</th><th>Pasif</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($outlets as $outlet)
                        <tr data-id="{{ $outlet->id }}" @class(['is-inactive' => ! $outlet->is_active])
                            data-url-edit="{{ route('setup.outlets.edit', $outlet) }}" data-url-copy="{{ route('setup.outlets.copy', $outlet) }}"
                            data-url-destroy="{{ route('setup.outlets.destroy', $outlet) }}"
                            data-url-up="{{ route('setup.outlets.move', [$outlet, 'up']) }}" data-url-down="{{ route('setup.outlets.move', [$outlet, 'down']) }}">
                            <td class="text-muted">{{ $outlet->sort }}</td>
                            <td class="font-semibold">{{ $outlet->code }}</td>
                            <td>{{ $outlet->name }}</td>
                            <td>{{ $outlet->typeLabel() }}</td>
                            <td>{{ \App\Domain\Pos\Models\Outlet::SOURCES[$outlet->source_system] ?? $outlet->source_system }}</td>
                            <td class="text-right">{{ $outlet->simphony_rvc_number ?? '—' }}</td>
                            <td>@if ($outlet->defaultTransactionCode){{ $outlet->defaultTransactionCode->code }} {{ $outlet->defaultTransactionCode->name }}@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-right">{{ $outlet->transaction_codes_count }}</td>
                            <td>{{ $outlet->is_active ? '' : '✓' }}</td>
                        </tr>
                    @empty
                    @endforelse
                    <tr data-grid-empty @if ($outlets->isNotEmpty()) hidden @endif><td colspan="9" class="text-center text-muted py-6">Kayıt yok.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.outlets.create') }}" class="btn btn-side" data-hotkey="alt+n insert">Yeni <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="outlet-grid" data-action="edit" data-hotkey="alt+e">Düzenle <span class="kbd ml-auto">Alt+E</span></a>
            <a class="btn btn-side" data-needs-selection="outlet-grid" data-action="copy" data-hotkey="alt+c">Kopyala <span class="kbd ml-auto">Alt+C</span></a>
            <form method="POST" data-confirm="Seçili outlet silinsin mi? Kullanımdaysa silinmez, pasife almanız gerekir.">
                @csrf @method('DELETE')
                <button class="btn btn-side" data-needs-selection="outlet-grid" data-action="destroy" data-hotkey="alt+d delete">Sil <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <div class="h-3"></div>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="outlet-grid" data-action="up" data-hotkey="alt+u">↑ Yukarı <span class="kbd ml-auto">Alt+U</span></button></form>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="outlet-grid" data-action="down" data-hotkey="alt+o">↓ Aşağı <span class="kbd ml-auto">Alt+O</span></button></form>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">Kapat <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
