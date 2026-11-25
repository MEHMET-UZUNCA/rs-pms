@extends('layouts.app')
@section('title', $outlet->exists ? __('pms.outlet.edit_title', ['name' => $outlet->name]) : __('pms.outlet.new_title'))
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
        <span>{{ $outlet->exists ? __('pms.outlet.edit_title', ['name' => $outlet->name]) : ($copiedFrom ? __('pms.outlet.copy_title', ['name' => $copiedFrom->name]) : __('pms.outlet.new_title')) }}</span>
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
                    <label class="field-label" for="code">{{ __('pms.common.code') }} *</label>
                    <input id="code" name="code" value="{{ old('code', $outlet->code) }}" maxlength="10" class="input uppercase @error('code') input-error @enderror" required autofocus>
                </div>
                <div class="col-span-2">
                    <label class="field-label" for="name">{{ __('pms.common.name') }} *</label>
                    <input id="name" name="name" value="{{ old('name', $outlet->name) }}" maxlength="100" class="input @error('name') input-error @enderror" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label" for="type">{{ __('pms.common.type') }}</label>
                    <select id="type" name="type" class="input">
                        @foreach (array_keys(\App\Domain\Pos\Models\Outlet::TYPES) as $value)
                            <option value="{{ $value }}" @selected(old('type', $outlet->type) === $value)>{{ __('pms.outlet.types.'.$value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="source_system">{{ __('pms.outlet.source') }}</label>
                    <select id="source_system" name="source_system" class="input">
                        @foreach (array_keys(\App\Domain\Pos\Models\Outlet::SOURCES) as $value)
                            <option value="{{ $value }}" @selected(old('source_system', $outlet->source_system) === $value)>{{ __('pms.outlet.sources.'.$value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="field-label" for="simphony_rvc_number">{{ __('pms.outlet.rvc_number') }}</label>
                    <input id="simphony_rvc_number" name="simphony_rvc_number" value="{{ old('simphony_rvc_number', $outlet->simphony_rvc_number) }}" inputmode="numeric" class="input @error('simphony_rvc_number') input-error @enderror">
                </div>
                <div>
                    <label class="field-label" for="rsone_department_id">{{ __('pms.outlet.rsone_department') }}</label>
                    <input id="rsone_department_id" name="rsone_department_id" value="{{ old('rsone_department_id', $outlet->rsone_department_id) }}" inputmode="numeric" class="input @error('rsone_department_id') input-error @enderror">
                </div>
            </div>
            <div>
                <label class="field-label" for="default_transaction_code_id">{{ __('pms.outlet.default_code') }}</label>
                <select id="default_transaction_code_id" name="default_transaction_code_id" class="input" data-default-code>
                    <option value="">{{ __('pms.outlet.default_auto') }}</option>
                    @foreach ($selectedCodes as $code)
                        <option value="{{ $code->id }}" @selected($default === (int) $code->id)>{{ $code->code }} {{ $code->name }}</option>
                    @endforeach
                </select>
                <p class="text-[12px] text-muted mt-1">{{ __('pms.outlet.default_hint') }}</p>
            </div>
            <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $outlet->is_active ?? true))> {{ __('pms.common.active') }}</label>
            <p class="text-[12px] text-muted">{{ __('pms.outlet.rvc_hint') }}</p>
        </section>

        <section class="flex-1 min-w-0 min-h-0 flex flex-col max-lg:min-h-96">
            <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <b>{{ __('pms.outlet.department_codes') }}</b>
                <input class="input w-48" placeholder="{{ __('pms.outlet.filter_codes') }}" data-code-filter autocomplete="off">
                <label class="flex items-center gap-1.5"><input type="checkbox" data-code-only-selected> {{ __('pms.outlet.only_selected') }}</label>
                <span class="ml-auto text-muted text-[12px]"><span data-code-count>{{ $selectedCodes->count() }}</span> {{ __('pms.outlet.selected_count') }}</span>
            </div>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" data-code-list>
                    <thead><tr><th class="w-8"></th><th>{{ __('pms.common.number') }}</th><th>{{ __('pms.outlet.short') }}</th><th>{{ __('pms.common.description') }}</th><th>{{ __('pms.common.group') }}</th><th>{{ __('pms.outlet.linked_outlet') }}</th></tr></thead>
                    <tbody>
                    @foreach ($codes as $code)
                        @php $elsewhere = $code->outlet_id && (int) $code->outlet_id !== (int) $outlet->id; @endphp
                        <tr data-code @class(['is-inactive' => $elsewhere || ! $code->is_active])>
                            <td><input type="checkbox" name="codes[]" value="{{ $code->id }}" data-no="{{ $code->code }}" data-name="{{ $code->name }}"
                                @checked(in_array((int) $code->id, $assigned, true)) @disabled($elsewhere) aria-label="{{ $code->code }} {{ $code->name }}"></td>
                            <td class="font-semibold">{{ $code->code }}</td>
                            <td>{{ $code->category }}</td>
                            <td>{{ $code->name }}@if ($code->is_discount) <span class="text-muted text-[11px]">{{ __('pms.outlet.discount') }}</span>@endif</td>
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
        <span class="text-[12px] text-muted">{{ __('pms.outlet.other_outlet_hint') }}</span>
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.outlets.index', ['secili' => $outlet->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
