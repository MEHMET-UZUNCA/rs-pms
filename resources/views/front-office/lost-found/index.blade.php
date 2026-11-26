@extends('layouts.app')
@section('title', __('pms.lf.title'))
@section('content')
@php $q = fn (string $k) => $filters[$k] ?? ''; @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.lf.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.lf.subtitle') }}</span></div>
    <form method="GET" action="{{ route('lost-found.index') }}" class="grid gap-3 p-3 border-b border-line lg:grid-cols-2 shrink-0" data-lf-filter>
        @foreach (['lost' => 'l', 'found' => 'f'] as $kind => $p)
            <fieldset class="rounded border border-line p-2 grid grid-cols-[6rem_1fr_1fr] gap-x-2 gap-y-1.5 items-center">
                <legend class="px-1 font-semibold">{{ __('pms.lf.'.$kind.'_items') }}</legend>
                <span class="field-label !mb-0">{{ __('pms.lf.from_to') }}</span>
                <input name="{{ $p }}_from" value="{{ $q($p.'_from') }}" class="input" placeholder="-30" autocomplete="off" aria-label="{{ __('pms.lf.from') }}">
                <input name="{{ $p }}_to" value="{{ $q($p.'_to') }}" class="input" placeholder="t" autocomplete="off" aria-label="{{ __('pms.lf.to') }}">
                <span class="field-label !mb-0">{{ __('pms.lf.group') }}</span>
                <select name="{{ $p }}_group" class="input col-span-2"><option value=""></option>@foreach ($groups as $g)<option value="{{ $g->id }}" @selected((string) $q($p.'_group') === (string) $g->id)>{{ $g->name }}</option>@endforeach</select>
                <span class="field-label !mb-0">{{ __('pms.lf.item') }}</span>
                <input name="{{ $p }}_item" value="{{ $q($p.'_item') }}" class="input col-span-2" autocomplete="off" placeholder="{{ __('pms.lf.full_text') }}">
                <span class="field-label !mb-0">{{ __('pms.lf.room') }} / {{ __('pms.lf.place') }}</span>
                <input name="{{ $p }}_room" value="{{ $q($p.'_room') }}" class="input" autocomplete="off" aria-label="{{ __('pms.lf.room') }}">
                <select name="{{ $p }}_place" class="input" aria-label="{{ __('pms.lf.place') }}"><option value=""></option>@foreach ($places as $g)<option value="{{ $g->id }}" @selected((string) $q($p.'_place') === (string) $g->id)>{{ $g->name }}</option>@endforeach</select>
                <span class="field-label !mb-0">{{ __('pms.lf.'.$kind.'_by') }}</span>
                <input name="{{ $p }}_person" value="{{ $q($p.'_person') }}" class="input" autocomplete="off">
                <div class="flex flex-wrap gap-x-3 text-[12px]">
                    <label class="flex items-center gap-1"><input type="checkbox" name="{{ $p }}_matched" value="1" @checked($q($p.'_matched'))> {{ __('pms.lf.include_matched') }}</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="{{ $p }}_resolved" value="1" @checked($q($p.'_resolved'))> {{ __('pms.lf.include_resolved') }}</label>
                </div>
            </fieldset>
        @endforeach
        <div class="lg:col-span-2 flex gap-2">
            <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
            <a href="{{ route('lost-found.index') }}" class="btn">{{ __('pms.lf.clear_filter') }}</a>
        </div>
    </form>

    <div class="flex flex-1 min-h-0 max-lg:flex-col">
        @foreach (['lost' => $selectedLost, 'found' => $selectedFound] as $kind => $selected)
            <section class="flex-1 min-w-0 flex flex-col border-r border-line">
                <div class="flex items-center gap-2 px-3 py-1.5 border-b border-line bg-panel">
                    <b>{{ __('pms.lf.'.$kind.'_items') }}</b><span class="text-[12px] text-muted">{{ __('pms.common.records', ['count' => $lists[$kind]->count()]) }}</span>
                    <a href="{{ route('lost-found.create', $kind) }}" class="btn ml-auto" data-hotkey="{{ $kind === 'lost' ? 'alt+l' : 'alt+f' }}">{{ __('pms.lf.new_'.$kind) }} <span class="kbd">{{ $kind === 'lost' ? 'Alt+L' : 'Alt+F' }}</span></a>
                    <a class="btn" data-needs-selection="{{ $kind }}-grid" data-action="edit">{{ __('pms.common.edit') }}</a>
                </div>
                <div class="flex-1 min-h-40 overflow-auto">
                    <table class="grid-table" id="{{ $kind }}-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#{{ $kind }}-detail">
                        <thead><tr><th>{{ __('pms.lf.number') }}</th><th>{{ __('pms.lf.item') }}</th><th>{{ __('pms.lf.when') }}</th><th>{{ __('pms.lf.where') }}</th><th>{{ __('pms.lf.room') }}</th><th>{{ __('pms.lf.status') }}</th></tr></thead>
                        <tbody>
                        @foreach ($lists[$kind] as $item)
                            <tr data-id="{{ $item->id }}" data-url-detail="{{ route('lost-found.show', $item) }}" data-url-edit="{{ route('lost-found.edit', $item) }}" @class(['is-inactive' => $item->isResolved()])>
                                <td class="font-semibold">{{ $item->number }}</td><td>{{ \Illuminate\Support\Str::limit($item->description, 50) }}</td>
                                <td class="whitespace-nowrap">{{ $item->found_at?->format('d.m.Y H:i') }}</td>
                                <td>{{ $item->location ?: optional($places->firstWhere('id', $item->public_place_id))->name }}</td>
                                <td>{{ $item->room?->number }}</td><td>{{ __('pms.lf.statuses.'.$item->status) }}</td>
                            </tr>
                        @endforeach
                        <tr data-grid-empty @if ($lists[$kind]->isNotEmpty()) hidden @endif><td colspan="6" class="text-center text-muted py-4">{{ __('pms.common.no_records') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="{{ $kind }}-detail" class="border-t border-line h-56 overflow-auto bg-surface" data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'>
                    <p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>
                </div>
            </section>
        @endforeach
        <aside class="w-36 shrink-0 p-2 flex flex-col gap-1.5">
            <form method="POST" action="{{ route('lost-found.match') }}" data-lf-match data-confirm="{{ __('pms.lf.match_confirm') }}">
                @csrf
                <input type="hidden" name="lost_id"><input type="hidden" name="found_id">
                <button class="btn btn-side w-full" data-hotkey="alt+m">{{ __('pms.lf.match') }} <span class="kbd ml-auto">Alt+M</span></button>
            </form>
            <p class="text-[11px] text-muted">{{ __('pms.lf.match_hint') }}</p>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
<script>
    // Eşleştir: soldaki seçili kayıp ve sağdaki seçili bulunan eşya
    document.querySelector('[data-lf-match]')?.addEventListener('submit', (e) => {
        const lost = document.querySelector('#lost-grid tr.is-selected')?.dataset.id;
        const found = document.querySelector('#found-grid tr.is-selected')?.dataset.id;
        if (!lost || !found) { e.preventDefault(); e.stopImmediatePropagation(); alert(@json(__('pms.lf.match_hint'))); return; }
        e.target.lost_id.value = lost; e.target.found_id.value = found;
    }, true);
</script>
@endsection
