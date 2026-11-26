@extends('layouts.app')
@section('title', __('pms.ooo.title'))
@section('content')
@php $d = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('d.m.Y'); $canEdit = Gate::allows('permission', 'housekeeping.outage'); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.ooo.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.business_date') }} {{ $businessDate->format('d.m.Y') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex items-end gap-0.5 px-3 pt-2 border-b border-line" role="tablist">
                @foreach ($counts as $t => $n)
                    <a href="{{ route('rooms.out-of-order', ['sekme' => $t, 'tur' => $type, 'oda' => $room]) }}" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}"
                       data-hotkey="alt+{{ $loop->iteration }}" @class(['px-3 h-8 -mb-px inline-flex items-center gap-2 border rounded-t text-[13px]', 'bg-surface border-line border-b-surface font-semibold' => $tab === $t, 'border-transparent text-muted' => $tab !== $t])>
                        {{ __('pms.ooo.tabs.'.$t) }} <span class="text-[11px] px-1.5 rounded bg-panel">{{ $n }}</span> <span class="kbd">Alt+{{ $loop->iteration }}</span></a>
                @endforeach
                <form method="GET" class="ml-auto flex items-center gap-2 pb-1.5">
                    <input type="hidden" name="sekme" value="{{ $tab }}">
                    <select name="tur" class="input w-40" onchange="this.form.submit()" aria-label="{{ __('pms.common.type') }}">
                        <option value="">{{ __('pms.ooo.all_types') }}</option>
                        <option value="ooo" @selected($type === 'ooo')>OOO · Out of Order</option><option value="oos" @selected($type === 'oos')>OOS · Out of Service</option>
                    </select>
                    <input name="oda" value="{{ $room }}" class="input w-24" placeholder="{{ __('pms.lf.room') }}" autocomplete="off">
                    <button class="btn" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd">F3</span></button>
                </form>
            </div>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="ooo-grid" data-grid data-selected="{{ $selected }}">
                    <thead><tr><th>{{ __('pms.lf.room') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.common.type') }}</th><th>{{ __('pms.ooo.from') }}</th><th>{{ __('pms.ooo.to_exclusive') }}</th>
                        <th>{{ __('pms.ooo.reason') }}</th><th>{{ __('pms.lf.note') }}</th><th>{{ __('pms.ooo.return_status') }}</th><th>{{ __('pms.ooo.entered_by') }}</th><th>{{ __('pms.ooo.released') }}</th></tr></thead>
                    <tbody>
                    @foreach ($outages as $o)
                        <tr data-id="{{ $o->id }}" @if (! $o->released_at && $canEdit) data-url-release="{{ route('housekeeping.release', $o->id) }}" @endif data-url-history="{{ route('rooms.history', ['oda' => $o->number]) }}">
                            <td class="font-semibold">{{ $o->number }}</td><td>{{ $o->room_type }}</td>
                            <td><span @class(['font-semibold', 'text-danger' => $o->type === 'ooo', 'text-amber-600' => $o->type === 'oos'])>{{ strtoupper($o->type) }}</span></td>
                            <td>{{ $d($o->date_from) }}</td><td>{{ $d($o->date_to) }}</td>
                            <td>{{ $o->reason }}</td><td>{{ $o->note }}</td><td>{{ __('pms.ooo.hk.'.$o->return_hk_status) }}</td>
                            <td class="text-[12px]">{{ $o->created_by }}</td>
                            <td class="text-[12px]">@if ($o->released_at){{ \Illuminate\Support\Carbon::parse($o->released_at)->format('d.m.Y H:i') }} {{ $o->released_by }}@endif</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($outages->isNotEmpty()) hidden @endif><td colspan="10" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
            @if ($canEdit)
                <form method="POST" action="{{ route('housekeeping.outage') }}" class="border-t border-line p-3 grid gap-2 md:grid-cols-[8rem_9rem_9rem_9rem_minmax(0,1fr)_minmax(0,1.5fr)_auto] items-end" data-new-outage>
                    @csrf
                    <div><label class="field-label" for="ooo-room">{{ __('pms.lf.room') }} *</label>
                        <select id="ooo-room" name="rooms[]" class="input" required><option value=""></option>@foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->number }}{{ $r->fo_status === 'occupied' ? ' ●' : '' }}</option>@endforeach</select></div>
                    <div><label class="field-label" for="ooo-type">{{ __('pms.common.type') }}</label>
                        <select id="ooo-type" name="type" class="input"><option value="OOO">OOO</option><option value="OOS">OOS</option></select></div>
                    <div><label class="field-label" for="ooo-from">{{ __('pms.ooo.from') }} *</label><input id="ooo-from" name="date_from" class="input" value="t" required autocomplete="off"></div>
                    <div><label class="field-label" for="ooo-to">{{ __('pms.ooo.to_exclusive') }} *</label><input id="ooo-to" name="date_to" class="input" value="+1" required autocomplete="off"></div>
                    <div><label class="field-label" for="ooo-reason">{{ __('pms.ooo.reason') }} *</label>
                        <select id="ooo-reason" name="reason_code_id" class="input" required>@foreach ($reasons as $r)<option value="{{ $r->id }}">{{ $r->code }} {{ $r->name }}</option>@endforeach</select></div>
                    <div><label class="field-label" for="ooo-note">{{ __('pms.lf.note') }}</label><input id="ooo-note" name="note" class="input" maxlength="500"></div>
                    <button class="btn btn-primary" data-hotkey="alt+n">{{ __('pms.ooo.add') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">Alt+N</span></button>
                </form>
            @endif
        </section>
        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @if ($canEdit)
                <form method="POST" data-confirm="{{ __('pms.ooo.release_confirm') }}">@csrf
                    <button class="btn btn-side w-full" data-needs-selection="ooo-grid" data-action="release" data-hotkey="alt+r">{{ __('pms.ooo.release') }} <span class="kbd ml-auto">Alt+R</span></button></form>
            @endif
            <a class="btn btn-side" data-needs-selection="ooo-grid" data-action="history" data-hotkey="alt+h">{{ __('pms.menu.room_history') }} <span class="kbd ml-auto">Alt+H</span></a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@endsection
