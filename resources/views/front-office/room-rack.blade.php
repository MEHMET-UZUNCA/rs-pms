@extends('layouts.app')
@section('title', __('pms.rack.title'))
@php
    $colors = ['in_house' => 'bg-ok', 'confirmed' => 'bg-brand-500', 'tentative' => 'bg-amber-600', 'ooo' => 'bg-slate-500', 'oos' => 'bg-slate-400'];
    $hk = ['clean' => 'bg-ok/15 text-ok', 'dirty' => 'bg-danger/10 text-danger', 'inspected' => 'bg-brand-100 text-brand-700', 'pickup' => 'bg-amber-100 text-amber-800'];
    $q = fn (array $over) => route('front-office.room-rack', array_filter(array_merge(['bas' => $start->format('d.m.Y'), 'gun' => $span, 'tip' => $typeId ?: null, 'kat' => $floorId ?: null, 'grup' => $grouped ? 1 : null], $over)));
    $canPost = Gate::allows('permission', 'cashiering.post');
    $cols = 'grid-template-columns: 7.5rem repeat('.$span.', minmax('.($span > 14 ? '2.2rem' : '4.5rem').', 1fr))';
    $canEdit = Gate::allows('permission', 'reservations.edit');
    $canBill = Gate::allows('permission', 'cashiering.billing');
    $canCheckIn = Gate::allows('permission', 'reservations.checkin');
@endphp
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.rack.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.rack.rooms_count', ['count' => $rooms->count()]) }}</span></div>

    <form method="GET" class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-line">
        <a href="{{ $q(['bas' => $start->subDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowleft">← {{ __('pms.rack.prev') }}</a>
        <a href="{{ $q(['bas' => 't']) }}" class="btn" data-hotkey="alt+home">{{ __('pms.rack.today') }}</a>
        <a href="{{ $q(['bas' => $start->addDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowright">{{ __('pms.rack.next') }} →</a>
        <label class="flex items-center gap-1.5 ml-2">{{ __('pms.rack.start') }} <input name="bas" value="{{ $start->format('d.m.Y') }}" class="input w-28"></label>
        <input type="hidden" name="gun" value="{{ $span }}">
        <select name="tip" class="input w-40" onchange="this.form.submit()"><option value="">{{ __('pms.rack.all_types') }}</option>
            @foreach ($types as $t)<option value="{{ $t->id }}" @selected($typeId === (int) $t->id)>{{ $t->code }}</option>@endforeach</select>
        <select name="kat" class="input w-36" onchange="this.form.submit()"><option value="">{{ __('pms.rack.all_floors') }}</option>
            @foreach ($floors as $f)<option value="{{ $f->id }}" @selected($floorId === (int) $f->id)>{{ $f->name }}</option>@endforeach</select>
        <input type="hidden" name="grup" value="{{ $grouped ? 1 : '' }}">
        <a href="{{ $q(['grup' => $grouped ? null : 1]) }}" @class(['btn', 'btn-primary' => $grouped]) data-hotkey="alt+t">{{ __('pms.rack.group_by_type') }} <span class="kbd">Alt+T</span></a>
        <div class="ml-auto flex gap-1">
            @foreach (\App\Http\Controllers\FrontOffice\RoomRackController::SPANS as $n)
                <a href="{{ $q(['gun' => $n]) }}" @class(['btn', 'btn-primary' => $n === $span])>{{ __('pms.rack.days', ['n' => $n]) }}</a>
            @endforeach
        </div>
    </form>

    <div class="flex-1 min-h-0 overflow-auto text-[12px] select-none" data-rack data-start="{{ $start->toDateString() }}" data-span="{{ $span }}"
         @if ($canEdit) data-new-url="{{ route('reservations.create') }}" data-can-move @endif
         data-msg-move="{{ __('pms.rack.confirm_move') }}" data-msg-resize="{{ __('pms.rack.confirm_resize') }}">
        <div class="grid sticky top-0 z-30 bg-surface border-b border-line min-w-max" style="{{ $cols }}">
            <div class="sticky left-0 z-10 bg-surface px-2 py-1.5 font-semibold">{{ __('pms.rack.room') }}</div>
            @foreach ($days as $d)
                <div @class(['px-1 py-1.5 text-center border-l border-line whitespace-nowrap', 'bg-amber-100 font-semibold' => $d['date']->isSameDay($businessDate), 'bg-brand-50' => $d['date']->isWeekend() && ! $d['date']->isSameDay($businessDate)])>
                    {{ __('pms.rack.weekdays')[$d['date']->dayOfWeekIso - 1] }}<br>{{ $d['date']->format('d.m') }}
                </div>
            @endforeach
        </div>

        @foreach ($rooms as $room)
            @if ($grouped && ($loop->first || $rooms[$loop->index - 1]->type_code !== $room->type_code))
                <div class="sticky left-0 px-2 py-1 bg-brand-50 border-b border-line text-[12.5px] font-semibold text-brand-700 min-w-max" data-type-group="{{ $room->type_code }}">{{ $room->type_code }} · {{ $room->type_name }} <span class="font-normal text-muted">({{ $rooms->where('type_code', $room->type_code)->count() }})</span></div>
            @endif
            <div class="grid border-b border-line/60 min-w-max hover:bg-brand-50/40" style="{{ $cols }}" data-room="{{ $room->number }}" data-room-id="{{ $room->id }}">
                <div class="sticky left-0 z-10 bg-panel px-2 py-1 flex items-center gap-1.5" style="grid-row: 1">
                    <b class="w-9">{{ $room->number }}</b><span class="text-muted w-8">{{ $room->type_code }}</span>
                    <span class="px-1 rounded text-[10px] {{ $hk[$room->hk_status] ?? '' }}" title="{{ __('pms.hk.fo_status.'.$room->fo_status) }} / {{ __('pms.hk.hk_status.'.$room->hk_status) }}">{{ __('pms.hk.short.'.$room->fo_status.'_'.($room->hk_status === 'dirty' ? 'dirty' : 'clean')) }}</span>
                </div>
                @foreach ($days as $i => $d)
                    <div class="border-l border-line/50 min-h-7 {{ $canEdit ? 'cursor-cell' : '' }}" style="grid-row: 1; grid-column: {{ $i + 2 }}" data-cell="{{ $i }}"></div>
                @endforeach
                @foreach ($bars[$room->id] ?? [] as $b)
                    @php
                        $isRes = (bool) $b['id'];
                        $actions = $isRes ? array_filter([
                            'card' => route('reservations.edit', $b['id']),
                            'folio' => $canBill && $b['status'] === 'in_house' ? route('billing.reservation', $b['id']) : null,
                            'checkin' => $canCheckIn && in_array($b['status'], ['confirmed', 'tentative'], true) && $b['arrival'] === $businessDate->toDateString() ? route('reservations.check-in', $b['id']) : null,
                            'checkout' => $canBill && $b['status'] === 'in_house' && $b['departure'] <= $businessDate->toDateString() ? route('billing.reservation', $b['id']) : null,
                            'post' => $canPost && $b['status'] === 'in_house' ? (string) $b['id'] : null,
                        ]) : [];
                    @endphp
                    <div title="{{ $b['title'] }}" data-kind="{{ $b['kind'] }}" @if ($isRes) data-bar="{{ $b['id'] }}" data-status="{{ $b['status'] }}" data-arrival="{{ $b['arrival'] }}" data-departure="{{ $b['departure'] }}"
                        data-actions='@json($actions)' data-move-url="{{ route('front-office.room-rack.move', $b['id']) }}" tabindex="0" role="button" @endif
                        class="relative {{ $colors[$b['kind']] }} text-white font-semibold rounded px-1.5 my-1 mx-0.5 truncate min-w-0 leading-5 hover:brightness-110 {{ $isRes ? 'cursor-pointer' : '' }} {{ $isRes && $canEdit ? 'cursor-grab' : '' }} {{ $b['cont_left'] ? 'rounded-l-none' : '' }} {{ $b['cont_right'] ? 'rounded-r-none' : '' }}"
                        style="grid-row: 1; grid-column: {{ $b['col'] + 2 }} / span {{ $b['len'] }}; contain: inline-size">{{ $b['cont_left'] ? '‹ ' : '' }}{{ $b['label'] }}@if ($isRes && $canEdit && ! $b['cont_right'])<span data-resize class="absolute right-0 top-0 bottom-0 w-2 cursor-ew-resize bg-white/30 rounded-r" title="{{ __('pms.rack.resize_hint') }}"></span>@endif</div>
                @endforeach
            </div>
        @endforeach

        <div class="grid sticky bottom-0 z-30 bg-surface border-t border-line min-w-max shadow-[0_-2px_4px_rgba(0,0,0,0.04)]" style="{{ $cols }}">
            <div class="sticky left-0 z-10 bg-surface px-2 py-1 font-semibold">{{ __('pms.rack.occupancy') }}</div>
            @foreach ($days as $d)<div class="px-1 py-1 text-center border-l border-line tabular-nums">{{ $d['occupied'] }}<span class="text-muted"> · {{ $d['pct'] }}%</span></div>@endforeach
            <div class="sticky left-0 z-10 bg-surface px-2 py-1 font-semibold">{{ __('pms.rack.unassigned') }}</div>
            @foreach ($days as $d)<div @class(['px-1 py-1 text-center border-l border-line tabular-nums', 'text-danger font-semibold' => $d['unassigned'] > 0])>{{ $d['unassigned'] ?: '' }}</div>@endforeach
        </div>
    </div>

    {{-- Çubuk işlem menüsü --}}
    <div id="rack-menu" hidden class="fixed z-50 min-w-44 bg-panel border border-line rounded shadow-lg py-1 text-[13px]" role="menu">
        @foreach (['card' => 'Enter', 'post' => 'P', 'folio' => 'B', 'checkin' => 'G', 'checkout' => 'O'] as $a => $key)
            <a data-menu-action="{{ $a }}" role="menuitem" class="flex items-center gap-3 px-3 py-1.5 hover:bg-brand-50 focus:bg-brand-50 outline-none" href="#">{{ __('pms.rack.actions.'.$a) }} <span class="kbd ml-auto">{{ $key }}</span></a>
        @endforeach
    </div>
    <form method="POST" id="rack-move-form" hidden>@csrf<input name="room_id"><input name="arrival_date"><input name="departure_date"></form>
    <form method="POST" id="rack-checkin-form" hidden>@csrf</form>
    @if ($canPost)
        {{-- Hızlı kayıt (Elektraweb Oda Planı → hızlı posting): folyoya gitmeden 1. pencereye harcama --}}
        <div id="rack-post-dialog" data-modal hidden class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center p-4">
            <form method="POST" action="{{ route('billing.post') }}" class="win w-[28rem] max-w-full" role="dialog" aria-modal="true" aria-labelledby="rack-post-title">
                @csrf
                <input type="hidden" name="reservation_id" data-post-reservation><input type="hidden" name="window" value="1">
                <div class="win-title"><span id="rack-post-title">{{ __('pms.rack.quick_post') }}</span><span class="font-normal text-brand-100 text-[12px]" data-post-guest></span></div>
                <div class="p-3 grid grid-cols-[6rem_1fr_4rem] gap-2">
                    <label><span class="field-label">{{ __('pms.billing.department_code') }}</span><input name="code" list="rack-codes" class="input" required autocomplete="off" data-autofocus></label>
                    <datalist id="rack-codes">@foreach ($manualCodes as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach</datalist>
                    <label><span class="field-label">{{ __('pms.billing.unit_price') }}</span><input name="amount" inputmode="decimal" class="input" required></label>
                    <label><span class="field-label">{{ __('pms.billing.qty') }}</span><input name="quantity" value="1" inputmode="decimal" class="input"></label>
                    <label class="col-span-3"><span class="field-label">{{ __('pms.billing.supplement') }}</span><input name="description" maxlength="100" class="input"></label>
                </div>
                <div class="flex gap-2 px-3 py-2 border-t border-line">
                    <button class="btn btn-primary ml-auto">{{ __('pms.billing.post_button') }}</button>
                    <button type="button" class="btn" data-modal-close data-hotkey="escape">{{ __('pms.common.cancel') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 px-3 py-1.5 border-t border-line text-[12px]">
        @foreach (['in_house', 'confirmed', 'tentative', 'ooo', 'oos'] as $k)
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded {{ $colors[$k] }}"></span>{{ __('pms.rack.legend.'.$k) }}</span>
        @endforeach
        <span class="ml-auto text-muted">{{ $canEdit ? __('pms.rack.drag_hint').' · ' : '' }}Alt+← / Alt+→ · Ctrl+L</span>
        <a href="{{ route('reservations.navigator') }}" class="btn" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</div>
@endsection
