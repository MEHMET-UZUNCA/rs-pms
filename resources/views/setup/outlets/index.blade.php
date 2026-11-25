@extends('layouts.app')
@section('title', __('pms.outlet.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.outlet.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.outlet.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.outlets.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">{{ __('pms.common.quick_search') }}
                    <input name="q" value="{{ $search }}" class="input w-56" data-grid-filter="outlet-grid" placeholder="{{ __('pms.common.code') }} / {{ __('pms.common.name') }}" autocomplete="off" autofocus>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> {{ __('pms.common.show_inactive') }}</label>
                <span class="ml-auto text-muted text-[12px]">{{ __('pms.outlet.count', ['count' => $outlets->count()]) }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="outlet-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th class="w-10">{{ __('pms.common.sort') }}</th><th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.outlet.source') }}</th><th class="text-right">{{ __('pms.outlet.rvc') }}</th>
                        <th>{{ __('pms.outlet.default_code') }}</th><th class="text-right">{{ __('pms.outlet.code_count') }}</th><th>{{ __('pms.common.inactive') }}</th>
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
                            <td>{{ __('pms.outlet.types.'.$outlet->type) }}</td>
                            <td>{{ __('pms.outlet.sources.'.$outlet->source_system) }}</td>
                            <td class="text-right">{{ $outlet->simphony_rvc_number ?? '—' }}</td>
                            <td>@if ($outlet->defaultTransactionCode){{ $outlet->defaultTransactionCode->code }} {{ $outlet->defaultTransactionCode->name }}@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-right">{{ $outlet->transaction_codes_count }}</td>
                            <td>{{ $outlet->is_active ? '' : '✓' }}</td>
                        </tr>
                    @empty
                    @endforelse
                    <tr data-grid-empty @if ($outlets->isNotEmpty()) hidden @endif><td colspan="9" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.outlets.create') }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="outlet-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <a class="btn btn-side" data-needs-selection="outlet-grid" data-action="copy" data-hotkey="alt+c">{{ __('pms.common.copy') }} <span class="kbd ml-auto">Alt+C</span></a>
            <form method="POST" data-confirm="{{ __('pms.common.delete_confirm') }}">
                @csrf @method('DELETE')
                <button class="btn btn-side" data-needs-selection="outlet-grid" data-action="destroy" data-hotkey="alt+d delete">{{ __('pms.common.delete') }} <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <div class="h-3"></div>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="outlet-grid" data-action="up" data-hotkey="alt+u">↑ {{ __('pms.common.up') }} <span class="kbd ml-auto">Alt+U</span></button></form>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="outlet-grid" data-action="down" data-hotkey="alt+o">↓ {{ __('pms.common.down') }} <span class="kbd ml-auto">Alt+O</span></button></form>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
