@extends('layouts.app')
@section('title', __('pms.rates.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.rates.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.rates.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-lg:hidden">@include('setup._tree')</aside>

        <section class="w-[30rem] shrink-0 min-w-0 flex flex-col border-r border-line max-md:w-full">
            <form method="GET" action="{{ route('setup.rates.index') }}" class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-line">
                <label class="flex items-center gap-2">{{ __('pms.rates.code') }}
                    <input name="q" value="{{ $search }}" class="input w-40" data-grid-filter="rate-grid" autocomplete="off" autofocus></label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="pasif" value="1" @checked($showInactive) onchange="this.form.submit()"> {{ __('pms.rates.show_inactive') }}</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" name="web" value="1" @checked($webOnly) onchange="this.form.submit()"> {{ __('pms.rates.show_web') }}</label>
                <span class="ml-auto text-muted text-[12px]">{{ __('pms.common.records', ['count' => $count]) }}</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="rate-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#rate-summary">
                    <thead><tr><th>{{ __('pms.rates.code') }} / {{ __('pms.common.description') }}</th><th>{{ __('pms.rates.board') }}</th><th>{{ __('pms.rates.currency_short') }}</th><th class="text-right">{{ __('pms.rates.periods') }}</th></tr></thead>
                    <tbody>
                    @foreach ($groups as $group)
                        <tr class="bg-panel" data-category><td colspan="4" class="font-semibold">⊟ {{ $group['category']?->name ?? __('pms.rates.no_category') }}</td></tr>
                        @foreach ($group['codes'] as $rate)
                            <tr data-id="{{ $rate->id }}" @class(['is-inactive' => ! $rate->is_active])
                                data-url-detail="{{ route('setup.rates.summary', $rate) }}" data-url-edit="{{ route('setup.rates.edit', $rate) }}"
                                data-url-destroy="{{ route('setup.rates.destroy', $rate) }}" data-url-copy="{{ route('setup.rates.copy', $rate) }}">
                                <td class="pl-6"><span class="font-semibold">{{ $rate->code }}</span> / {{ $rate->name }}@if ($rate->is_public) <span class="text-[10px] px-1 rounded bg-brand-50 text-brand-700">WEB</span>@endif</td>
                                <td>{{ $rate->package?->code ?? '' }}</td>
                                <td>{{ $rate->currency_code }}</td>
                                <td class="text-right">{{ $rate->amounts_count ?: '' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr data-grid-empty @if ($count) hidden @endif><td colspan="4" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="rate-summary" class="flex-1 min-w-0 overflow-auto bg-surface max-md:hidden" data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'>
            <p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>
        </section>

        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <a href="{{ route('setup.rates.create') }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></a>
            <a class="btn btn-side" data-needs-selection="rate-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Alt+E</span></a>
            <form method="POST" data-copy-form>
                @csrf
                <input type="hidden" name="code" data-copy-code>
                <button class="btn btn-side w-full" data-needs-selection="rate-grid" data-action="copy" data-hotkey="alt+c" data-prompt="{{ __('pms.rates.copy_prompt') }}">{{ __('pms.common.copy') }} <span class="kbd ml-auto">Alt+C</span></button>
            </form>
            <form method="POST" data-confirm="{{ __('pms.rates.delete_confirm') }}">
                @csrf @method('DELETE')
                <button class="btn btn-side w-full" data-needs-selection="rate-grid" data-action="destroy" data-hotkey="alt+d delete">{{ __('pms.common.delete') }} <span class="kbd ml-auto">Alt+D</span></button>
            </form>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
<script>
    // Kopyala: yeni fiyat kodunu sorar (Fidelio Copy)
    document.querySelector('[data-copy-form]')?.addEventListener('submit', (e) => {
        const button = e.target.querySelector('[data-prompt]');
        const code = window.prompt(button.dataset.prompt);
        if (!code) { e.preventDefault(); return; }
        e.target.querySelector('[data-copy-code]').value = code.trim().toUpperCase();
    });
</script>
@endsection
