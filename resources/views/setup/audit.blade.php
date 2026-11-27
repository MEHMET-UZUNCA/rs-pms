@extends('layouts.app')
@section('title', __('pms.audit_log.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.audit_log.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.records', ['count' => $rows->count()]) }}</span></div>
    <form method="GET" class="flex flex-wrap items-end gap-2 px-3 py-2 border-b border-line">
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span><input name="bas" value="{{ $from->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.to') }}</span><input name="bit" value="{{ $to->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.audit_log.type') }}</span>
            <select name="tur" class="input w-44"><option value="">{{ __('pms.audit_log.all') }}</option>@foreach ($types as $t)<option value="{{ $t }}" @selected($type === $t)>{{ __('pms.audit_log.types.'.$t) === 'pms.audit_log.types.'.$t ? $t : __('pms.audit_log.types.'.$t) }}</option>@endforeach</select></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.audit_log.record') }}</span><input name="kayit" value="{{ $id }}" inputmode="numeric" class="input w-24"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.moves.user') }}</span>
            <select name="kullanici" class="input w-40"><option value="">{{ __('pms.audit_log.all') }}</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($user === (int) $u->id)>{{ $u->username }}</option>@endforeach</select></label>
        <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
    </form>
    <div class="flex-1 min-h-0 overflow-auto">
        <table class="grid-table min-w-[56rem]" data-audit>
            <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.moves.user') }}</th><th>{{ __('pms.audit_log.type') }}</th><th>{{ __('pms.audit_log.record') }}</th><th>{{ __('pms.audit_log.action') }}</th><th class="w-full">{{ __('pms.audit_log.changes') }}</th></tr></thead>
            <tbody>
            @forelse ($rows as $log)
                <tr class="align-top"><td class="tabular-nums">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d.m.Y H:i') }}</td><td>{{ $log->username ?? __('pms.audit_log.system') }}</td>
                    <td>{{ __('pms.audit_log.types.'.$log->auditable_type) === 'pms.audit_log.types.'.$log->auditable_type ? $log->auditable_type : __('pms.audit_log.types.'.$log->auditable_type) }}</td>
                    <td>@if ($log->auditable_type === 'reservation')<a class="text-brand-600 hover:underline" href="{{ route('reservations.edit', $log->auditable_id) }}">#{{ $log->auditable_id }}</a>@else #{{ $log->auditable_id }}@endif</td>
                    <td>{{ __('pms.audit_log.actions.'.$log->action) === 'pms.audit_log.actions.'.$log->action ? $log->action : __('pms.audit_log.actions.'.$log->action) }}</td>
                    <td class="text-[12.5px]">@include('partials.audit-changes', ['log' => $log])</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><span class="text-[12px] text-muted">{{ __('pms.audit_log.hint') }}</span><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
