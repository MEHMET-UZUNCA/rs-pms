@extends('layouts.app')
@section('title', __('pms.moves.title'))
@section('content')
@php $canEdit = Gate::allows('permission', 'reservations.edit'); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.moves.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.records', ['count' => $rows->count()]) }}</span></div>
    <form method="GET" class="flex flex-wrap items-end gap-3 px-3 py-2 border-b border-line">
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span><input name="bas" value="{{ $from->format('d.m.Y') }}" class="input w-28" autocomplete="off" autofocus></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.to') }}</span><input name="bit" value="{{ $to->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
    </form>
    <div class="flex-1 min-h-0 overflow-auto">
        <table class="grid-table min-w-[48rem]" data-room-moves>
            <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.res.conf_no') }}</th><th>{{ __('pms.prof.name') }}</th><th>{{ __('pms.moves.from_room') }}</th><th>{{ __('pms.moves.to_room') }}</th>
                <th class="w-72">{{ __('pms.moves.reason') }}</th><th>{{ __('pms.moves.user') }}</th></tr></thead>
            <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($m->business_date)->format('d.m.Y') }} <span class="text-muted text-[11px]">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('H:i') }}</span></td>
                    <td><a class="text-brand-600 hover:underline" href="{{ route('reservations.edit', $m->reservation_id) }}">{{ $m->confirmation_number }}</a></td>
                    <td class="font-semibold">{{ $m->display_name }}</td>
                    <td>{{ $m->from_room ?? '—' }} <span class="text-muted text-[11px]">{{ $m->from_type }}</span></td>
                    <td>{{ $m->to_room }} <span class="text-muted text-[11px]">{{ $m->to_type }}</span>@if ($m->from_type && $m->from_type !== $m->to_type) <span class="text-[11px] text-brand-700">{{ __('pms.moves.type_changed') }}</span>@endif</td>
                    <td>@if ($canEdit)
                            <form method="POST" action="{{ route('room-moves.reason', $m->id) }}" class="flex gap-1">@csrf
                                <select name="reason_code_id" class="input" onchange="this.form.submit()" aria-label="{{ __('pms.moves.reason') }}"><option value="">—</option>
                                    @foreach ($reasons as $r)<option value="{{ $r->id }}" @selected((int) $m->reason_code_id === (int) $r->id)>{{ $r->code }} {{ $r->name }}</option>@endforeach</select></form>
                        @else{{ $reasons->firstWhere('id', $m->reason_code_id)?->name }}@endif</td>
                    <td>{{ $m->username }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-6">{{ __('pms.moves.none') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
