@extends('layouts.app')
@section('title', __('pms.block.title'))
@section('content')
@php $canEdit = Gate::allows('permission', 'reservations.edit'); $d = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y') : ''; @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.block.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.block.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" class="flex flex-wrap items-end gap-3 px-3 py-2 border-b border-line">
                <div class="flex gap-0.5" role="tablist">
                    @foreach (['active', 'past', 'cancelled'] as $i => $s)
                        <a href="{{ route('blocks.index', ['durum' => $s, 'ara' => $q ?: null]) }}" role="tab" aria-selected="{{ $status === $s ? 'true' : 'false' }}" data-hotkey="alt+{{ $i + 1 }}"
                           @class(['px-3 h-8 inline-flex items-center gap-2 border rounded text-[13px]', 'bg-brand-50 border-brand-200 font-semibold' => $status === $s, 'border-line text-muted' => $status !== $s])>
                            {{ __('pms.block.filters.'.$s) }} <span class="kbd">Alt+{{ $i + 1 }}</span></a>
                    @endforeach
                </div>
                <input type="hidden" name="durum" value="{{ $status }}">
                <label class="flex flex-col"><span class="field-label">{{ __('pms.common.search') }}</span><input name="ara" value="{{ $q }}" class="input w-56" autofocus autocomplete="off" placeholder="{{ __('pms.block.search_hint') }}"></label>
                <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="block-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr><th>{{ __('pms.common.code') }}</th><th>{{ __('pms.common.name') }}</th><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.res.status') }}</th>
                        <th>{{ __('pms.block.agent') }}</th><th>{{ __('pms.block.start') }}</th><th>{{ __('pms.block.end') }}</th><th>{{ __('pms.res.rate_code') }}</th>
                        <th class="text-right">{{ __('pms.block.allotted') }}</th><th class="text-right">{{ __('pms.block.picked_up') }}</th><th>{{ __('pms.block.cutoff') }}</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $b)
                        @php $c = $counts[$b->id] ?? ['allotted' => 0, 'picked_up' => 0]; @endphp
                        <tr data-id="{{ $b->id }}" data-url-edit="{{ route('blocks.show', $b->id) }}" @class(['is-inactive' => $b->status === 'cancelled'])>
                            <td class="font-semibold">{{ $b->code }}</td><td>{{ $b->name }}</td><td>{{ __('pms.block.types.'.$b->type) }}</td>
                            <td @class(['text-warning' => $b->status === 'tentative'])>{{ __('pms.block.statuses.'.$b->status) }}</td><td>{{ $b->agent }}</td>
                            <td>{{ $d($b->start_date) }}</td><td>{{ $d($b->end_date) }}</td><td>{{ $b->rate_code }}</td>
                            <td class="text-right tabular-nums">{{ $c['allotted'] }}</td><td class="text-right tabular-nums">{{ $c['picked_up'] }}</td><td>{{ $d($b->cutoff_date) }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="11" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @if ($canEdit)
                <a href="{{ route('blocks.create', ['tur' => 'group']) }}" class="btn btn-side" data-hotkey="alt+n insert">{{ __('pms.block.new_group') }} <span class="kbd ml-auto">Alt+N</span></a>
                <a href="{{ route('blocks.create', ['tur' => 'allotment']) }}" class="btn btn-side" data-hotkey="alt+k">{{ __('pms.block.new_allotment') }} <span class="kbd ml-auto">Alt+K</span></a>
            @endif
            <a class="btn btn-side" data-needs-selection="block-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.block.open') }} <span class="kbd ml-auto">Enter</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
