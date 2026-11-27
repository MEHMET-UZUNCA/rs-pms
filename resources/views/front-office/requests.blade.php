@extends('layouts.app')
@section('title', __('pms.req.title'))
@section('content')
@php $Svc = \App\Domain\FrontOffice\Services\GuestRequestService::class; @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.req.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.req.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <form method="GET" class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-line">
                @foreach (\App\Http\Controllers\FrontOffice\GuestRequestController::TABS as $key => $hotkey)
                    <a href="{{ route('requests.index', ['sekme' => $key, 'departman' => $department]) }}" data-hotkey="{{ $hotkey }}"
                       @class(['inline-flex items-center gap-1.5 h-8 px-3 rounded-full border text-[13px] whitespace-nowrap',
                           'bg-brand-600 border-brand-600 text-white' => $tab === $key, 'bg-white border-line hover:bg-brand-50' => $tab !== $key,
                           '!border-danger !text-danger' => $key === 'overdue' && $counts['overdue'] > 0 && $tab !== $key])>
                        {{ __('pms.req.tabs.'.$key) }}@if (isset($counts[$key])) <b>{{ $counts[$key] }}</b>@endif <span class="text-[11px] opacity-70">{{ str_replace('alt+', 'Alt+', $hotkey) }}</span></a>
                @endforeach
                <input type="hidden" name="sekme" value="{{ $tab }}">
                <select name="departman" class="input !w-48 ml-auto" onchange="this.form.submit()" aria-label="{{ __('pms.req.department') }}">
                    <option value="">{{ __('pms.req.all_departments') }}</option>
                    @foreach ($Svc::DEPARTMENTS as $d)<option value="{{ $d }}" @selected($department === $d)>{{ __('pms.req.departments.'.$d) }}</option>@endforeach
                </select>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table min-w-[48rem]" id="req-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#req-detail">
                    <thead><tr><th>{{ __('pms.req.due') }}</th><th>{{ __('pms.req.kind') }}</th><th>{{ __('pms.card.room') }}</th><th>{{ __('pms.req.guest') }}</th>
                        <th>{{ __('pms.req.department') }}</th><th class="w-full">{{ __('pms.req.message') }}</th><th>{{ __('pms.res.status') }}</th><th>{{ __('pms.req.by') }}</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $t)
                        @php $late = $Svc::isOverdue($t); @endphp
                        <tr data-id="{{ $t->id }}" data-url-detail="{{ route('requests.show', $t->id) }}" @class(['is-inactive' => in_array($t->status, ['done', 'cancelled'], true)])>
                            <td @class(['tabular-nums', 'text-danger font-semibold' => $late])>{{ \Illuminate\Support\Carbon::parse($t->trace_date)->format('d.m') }} {{ substr((string) $t->trace_time, 0, 5) }}@if ($late) · {{ __('pms.req.late') }}@endif</td>
                            <td>@if ($t->priority === 'high')<span class="text-danger" title="{{ __('pms.req.high') }}">●</span> @endif{{ __('pms.req.kinds.'.$t->kind) }}</td>
                            <td class="font-semibold">{{ $t->room }}</td><td>{{ $t->guest_name }}</td><td>{{ __('pms.req.departments.'.$t->department) }}</td>
                            <td class="max-w-0 truncate" title="{{ $t->message }}">{{ $t->message }}</td>
                            <td><span @class(['px-2 py-0.5 rounded-full text-[12px]', 'bg-brand-50 text-brand-700' => $t->status === 'open', 'bg-amber-100 text-amber-800' => $t->status === 'in_progress',
                                'bg-ok/10 text-ok' => $t->status === 'done', 'bg-surface text-muted' => $t->status === 'cancelled'])>{{ __('pms.req.statuses.'.$t->status) }}</span></td>
                            <td class="text-muted">{{ $t->username }}</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="8" class="text-center text-muted py-6">{{ __('pms.req.none') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="req-detail" class="border-t border-line h-56 overflow-auto bg-surface shrink-0" data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'><p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p></div>
        </section>
        <aside class="w-44 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            <button type="button" class="btn btn-side" data-modal-open="request-dialog" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></button>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>

<div id="request-dialog" data-modal @if (! $errors->any()) hidden @endif class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center p-4">
    <form method="POST" action="{{ route('requests.store') }}" class="win w-[34rem] max-w-full" role="dialog" aria-modal="true" aria-labelledby="request-title">
        @csrf
        <div class="win-title"><span id="request-title">{{ __('pms.req.new') }}</span></div>
        @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <div class="p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
            <label><span class="field-label">{{ __('pms.card.room') }}</span><input name="room" value="{{ old('room') }}" maxlength="10" class="input" data-autofocus autocomplete="off"></label>
            <label class="col-span-1 sm:col-span-3"><span class="field-label">{{ __('pms.req.guest') }}</span><input name="guest_name" value="{{ old('guest_name') }}" maxlength="150" class="input" placeholder="{{ __('pms.req.guest_hint') }}"></label>
            <label><span class="field-label">{{ __('pms.req.kind') }}</span><select name="kind" class="input">@foreach ($Svc::KINDS as $k)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ __('pms.req.kinds.'.$k) }}</option>@endforeach</select></label>
            <label class="sm:col-span-2"><span class="field-label">{{ __('pms.req.department') }}</span><select name="department" class="input">@foreach ($Svc::DEPARTMENTS as $d)<option value="{{ $d }}" @selected(old('department') === $d)>{{ __('pms.req.departments.'.$d) }}</option>@endforeach</select></label>
            <label><span class="field-label">{{ __('pms.req.priority') }}</span><select name="priority" class="input"><option value="normal">{{ __('pms.req.normal') }}</option><option value="high" @selected(old('priority') === 'high')>{{ __('pms.req.high') }}</option></select></label>
            <label><span class="field-label">{{ __('pms.req.due_date') }}</span><input name="due_date" value="{{ old('due_date', $defaultDue->format('d.m.Y')) }}" class="input" autocomplete="off"></label>
            <label><span class="field-label">{{ __('pms.req.due') }}</span><input name="due_time" type="time" value="{{ old('due_time', $defaultDue->format('H:i')) }}" class="input" required></label>
            <label class="col-span-2 sm:col-span-4"><span class="field-label">{{ __('pms.req.message') }} *</span><textarea name="message" rows="3" maxlength="1000" class="input !h-auto py-1" required>{{ old('message') }}</textarea></label>
        </div>
        <div class="flex gap-2 px-3 py-2 border-t border-line">
            <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
            <button type="button" class="btn" data-modal-close data-hotkey="escape">{{ __('pms.common.cancel') }}</button>
        </div>
    </form>
</div>
@endsection
