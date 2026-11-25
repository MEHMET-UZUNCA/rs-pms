@extends('layouts.app')
@section('title', $outlet->exists ? "Outlet — {$outlet->name}" : 'Yeni outlet')
@section('content')
@php
    $action = $outlet->exists ? route('setup.outlets.update', $outlet) : route('setup.outlets.store');
    $selectedCodes = $codes->whereIn('id', $assigned);
    $default = (int) old('default_transaction_code_id', $outlet->default_transaction_code_id);
@endphp
<form method="POST" action="{{ $action }}" class="win flex-1">
    @csrf
    @if ($outlet->exists) @method('PUT') @endif
    <div class="win-title">
        <span>Outlet — {{ $outlet->exists ? 'Düzenle: '.$outlet->name : ($copiedFrom ? 'Kopyala: '.$copiedFrom->name : 'Yeni') }}</span>
    </div>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="flex flex-1 min-h-0 max-lg:flex-col">
        <section class="w-80 shrink-0 p-3 flex flex-col gap-2.5 border-r border-line max-lg:w-full max-lg:border-r-0 max-lg:border-b">
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="field-label" for="code">Kod *</label>
                    <input id="code" name="code" value="{{ old('code', $outlet->code) }}" maxlength="10" class="input uppercase @error('code') input-error @enderror" required autofocus>
                </div>
                <div class="col-span-2">
                    <label class="field-label" for="name">Ad *</label>
                    <input id="name" name="name" value="{{ old('name', $outlet->name) }}" maxlength="100" class="input @error('name') input-error @enderror" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label" for="type">Tip</label>
                    <select id="type" name="type" class="input">
                        @foreach (\App\Domain\Pos\Models\Outlet::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $outlet->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="source_system">Kaynak</label>
                    <select id="source_system" name="source_system" class="input">
                        @foreach (\App\Domain\Pos\Models\Outlet::SOURCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('source_system', $outlet->source_system) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label" for="simphony_rvc_number">Simphony RVC no</label>
                    <input id="simphony_rvc_number" name="simphony_rvc_number" value="{{ old('simphony_rvc_number', $outlet->simphony_rvc_number) }}" inputmode="numeric" class="input @error('simphony_rvc_number') input-error @enderror">
                </div>
                <div>
                    <label class="field-label" for="rsone_department_id">RS ONE departmanı</label>
                    <input id="rsone_department_id" name="rsone_department_id" value="{{ old('rsone_department_id', $outlet->rsone_department_id) }}" inputmode="numeric" class="input @error('rsone_department_id') input-error @enderror">
                </div>
            </div>
            <div>
                <label class="field-label" for="default_transaction_code_id">Varsayılan departman kodu</label>
                <select id="default_transaction_code_id" name="default_transaction_code_id" class="input" data-default-code>
                    <option value="">— otomatik (en küçük numara) —</option>
                    @foreach ($selectedCodes as $code)
                        <option value="{{ $code->id }}" @selected($default === (int) $code->id)>{{ $code->code }} {{ $code->name }}</option>
                    @endforeach
                </select>
                <p class="text-[12px] text-muted mt-1">Simphony'den alt toplam eşlemesi olmayan tutarlar bu koda yazılır.</p>
            </div>
            <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $outlet->is_active ?? true))> Aktif</label>
            <p class="text-[12px] text-muted">Simphony RVC numarası, Simphony EMC'deki gelir merkezi numarasıdır; Simphony'den gelen kayıt bu numarayla outlet'e bağlanır.</p>
        </section>

        <section class="flex-1 min-w-0 min-h-0 flex flex-col max-lg:min-h-96">
            <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <b>Departman kodları</b>
                <input class="input w-48" placeholder="Süz: numara ya da ad" data-code-filter autocomplete="off">
                <label class="flex items-center gap-1.5"><input type="checkbox" data-code-only-selected> Yalnız seçilenler</label>
                <span class="ml-auto text-muted text-[12px]"><span data-code-count>{{ $selectedCodes->count() }}</span> kod seçili</span>
            </div>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" data-code-list>
                    <thead><tr><th class="w-8"></th><th>No</th><th>Kısa</th><th>Açıklama</th><th>Grup</th><th>Bağlı outlet</th></tr></thead>
                    <tbody>
                    @foreach ($codes as $code)
                        @php $elsewhere = $code->outlet_id && (int) $code->outlet_id !== (int) $outlet->id; @endphp
                        <tr data-code @class(['is-inactive' => $elsewhere || ! $code->is_active])>
                            <td><input type="checkbox" name="codes[]" value="{{ $code->id }}" data-no="{{ $code->code }}" data-name="{{ $code->name }}"
                                @checked(in_array((int) $code->id, $assigned, true)) @disabled($elsewhere) aria-label="{{ $code->code }} {{ $code->name }}"></td>
                            <td class="font-semibold">{{ $code->code }}</td>
                            <td>{{ $code->category }}</td>
                            <td>{{ $code->name }}@if ($code->is_discount) <span class="text-muted text-[11px]">(indirim)</span>@endif</td>
                            <td>{{ $code->group_code }}</td>
                            <td>{{ $elsewhere ? $code->outlet_name : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <span class="text-[12px] text-muted">Başka outlet'e bağlı kodlar soluk gösterilir; önce o outlet'ten çıkarılmalıdır.</span>
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">Tamam <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.outlets.index', ['secili' => $outlet->id]) }}" class="btn" data-hotkey="escape">İptal <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
