@extends('layouts.app')
@section('title', 'Departman Kodları')
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>Ayarlar — Departman Kodları</span><span class="font-normal text-brand-100 text-[12px]">Fidelio "Department Codes": gelir, ödeme ve sistem kodları</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.transaction-codes.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">Hızlı arama
                    <input name="q" value="{{ $search }}" class="input w-48" data-grid-filter="code-grid" placeholder="No, kısa kod ya da ad" autocomplete="off" autofocus>
                </label>
                <label class="flex items-center gap-2">Grup
                    <select name="grup" class="input w-52" onchange="this.form.submit()">
                        <option value="">Tümü</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected($groupId === (int) $group->id)>{{ $group->code }} {{ $group->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> Pasifleri göster</label>
                <span class="ml-auto text-muted text-[12px]">{{ $codes->count() }} kod</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="code-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th>No</th><th>Kısa</th><th>Açıklama</th><th class="text-center">Elle</th><th>Grup</th><th>Birim</th>
                        <th>KDV</th><th>Outlet</th><th>Tip</th><th>Pasif</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($codes as $code)
                        <tr data-id="{{ $code->id }}" @class(['is-inactive' => ! $code->is_active])
                            data-url-edit="{{ route('setup.transaction-codes.edit', $code) }}" data-url-copy="{{ route('setup.transaction-codes.copy', $code) }}"
                            data-url-destroy="{{ route('setup.transaction-codes.destroy', $code) }}"
                            data-url-up="{{ route('setup.transaction-codes.move', [$code, 'up']) }}" data-url-down="{{ route('setup.transaction-codes.move', [$code, 'down']) }}">
                            <td class="font-semibold">{{ $code->code }}</td>
                            <td>{{ $code->category }}</td>
                            <td>{{ $code->name }}</td>
                            <td class="text-center text-ok">{{ $code->manual_post_allowed ? '✓' : '' }}</td>
                            <td>{{ $code->group?->code }}</td>
                            <td>{{ $code->unit_description }}</td>
                            <td>{{ $taxes[$code->id] ?? '' }}</td>
                            <td>{{ $code->outlet?->name }}</td>
                            <td class="text-muted">{{ \App\Http\Controllers\Setup\TransactionCodeController::TYPES[$code->type] ?? $code->type }}</td>
                            <td>{{ $code->is_active ? '' : '✓' }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($codes->isNotEmpty()) hidden @endif><td colspan="10" class="text-center text-muted py-6">Kayıt yok.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.transaction-codes.create') }}" class="btn btn-side" data-hotkey="alt+n insert">Yeni <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="code-grid" data-action="edit" data-hotkey="alt+e">Düzenle <span class="kbd ml-auto">Alt+E</span></a>
            <a class="btn btn-side" data-needs-selection="code-grid" data-action="copy" data-hotkey="alt+c">Kopyala <span class="kbd ml-auto">Alt+C</span></a>
            <form method="POST" data-confirm="Seçili departman kodu silinsin mi? Kullanımdaysa silinmez, pasife almanız gerekir.">
                @csrf @method('DELETE')
                <button class="btn btn-side" data-needs-selection="code-grid" data-action="destroy" data-hotkey="alt+d delete">Sil <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <div class="h-3"></div>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="code-grid" data-action="up" data-hotkey="alt+u">↑ Yukarı <span class="kbd ml-auto">Alt+U</span></button></form>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="code-grid" data-action="down" data-hotkey="alt+o">↓ Aşağı <span class="kbd ml-auto">Alt+O</span></button></form>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">Kapat <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
