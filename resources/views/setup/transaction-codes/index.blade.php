@extends('layouts.app')
@section('title', __('pms.dc.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.dc.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.dc.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>

        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" action="{{ route('setup.transaction-codes.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">{{ __('pms.common.quick_search') }}
                    <input name="q" value="{{ $search }}" class="input w-48" data-grid-filter="code-grid" placeholder="{{ __('pms.dc.search_hint') }}" autocomplete="off" autofocus>
                </label>
                <label class="flex items-center gap-2">{{ __('pms.common.group') }}
                    <select name="grup" class="input w-52" onchange="this.form.submit()">
                        <option value="">{{ __('pms.common.all') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected($groupId === (int) $group->id)>{{ $group->code }} {{ $group->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> {{ __('pms.common.show_inactive') }}</label>
                <span class="ml-auto text-muted text-[12px]">{{ __('pms.dc.count', ['count' => $codes->count()]) }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="code-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr>
                        <th>{{ __('pms.common.number') }}</th><th>{{ __('pms.dc.code') }}</th><th>{{ __('pms.common.description') }}</th><th class="text-center">{{ __('pms.dc.manual') }}</th><th>{{ __('pms.common.group') }}</th><th>{{ __('pms.dc.unit') }}</th>
                        <th>{{ __('pms.dc.vat') }}</th><th>{{ __('pms.dc.outlet') }}</th><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.common.inactive') }}</th>
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
                            <td class="text-muted">{{ __('pms.dc.types.'.$code->type) }}</td>
                            <td>{{ $code->is_active ? '' : '✓' }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($codes->isNotEmpty()) hidden @endif><td colspan="10" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.transaction-codes.create') }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="code-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <a class="btn btn-side" data-needs-selection="code-grid" data-action="copy" data-hotkey="alt+c">{{ __('pms.common.copy') }} <span class="kbd ml-auto">Alt+C</span></a>
            <form method="POST" data-confirm="{{ __('pms.common.delete_confirm') }}">
                @csrf @method('DELETE')
                <button class="btn btn-side" data-needs-selection="code-grid" data-action="destroy" data-hotkey="alt+d delete">{{ __('pms.common.delete') }} <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <div class="h-3"></div>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="code-grid" data-action="up" data-hotkey="alt+u">↑ {{ __('pms.common.up') }} <span class="kbd ml-auto">Alt+U</span></button></form>
            <form method="POST">@csrf<button class="btn btn-side" data-needs-selection="code-grid" data-action="down" data-hotkey="alt+o">↓ {{ __('pms.common.down') }} <span class="kbd ml-auto">Alt+O</span></button></form>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
