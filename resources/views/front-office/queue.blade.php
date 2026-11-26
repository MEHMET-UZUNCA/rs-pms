@extends('layouts.app')
@section('title', __('pms.queue.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.queue.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.queue.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 overflow-auto">
            <table class="grid-table" id="queue-grid" data-grid>
                <thead><tr><th>{{ __('pms.lf.room') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.res.guest') }}</th><th>{{ __('pms.res.pax') }}</th><th>{{ __('pms.res.departure') }}</th>
                    <th>{{ __('pms.queue.room_status') }}</th><th>{{ __('pms.queue.since') }}</th><th class="text-right">{{ __('pms.queue.waiting') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr data-id="{{ $r->id }}" @class(['bg-green-50' => $r->ready]) data-ready="{{ $r->ready ? 1 : 0 }}"
                        @if ($r->ready && Gate::allows('permission', 'reservations.checkin')) data-url-checkin="{{ route('reservations.check-in', $r->id) }}" @endif
                        data-url-remove="{{ route('queue.remove', $r->id) }}" @can('permission', 'reservations.edit') data-url-edit="{{ route('reservations.edit', $r->id) }}" @endcan>
                        <td class="font-semibold">{{ $r->room ?? '—' }}</td><td>{{ $r->room_type }}</td><td>{{ $r->display_name }}</td>
                        <td>{{ $r->adults }}{{ $r->children ? '+'.$r->children : '' }}</td><td>{{ \Illuminate\Support\Carbon::parse($r->departure_date)->format('d.m.Y') }}</td>
                        <td>@if ($r->room)<span @class(['font-semibold', 'text-ok' => $r->ready, 'text-danger' => ! $r->ready])>{{ $r->ready ? __('pms.queue.ready') : __('pms.queue.not_ready') }}</span>
                            <span class="text-muted text-[12px]">{{ __('pms.history.fo.'.$r->fo_status) }} · {{ strtoupper($r->hk_status) }}</span>@else<span class="text-danger">{{ __('pms.queue.no_room') }}</span>@endif</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($r->queued_at)->format('H:i') }}</td>
                        <td class="text-right tabular-nums">{{ __('pms.queue.minutes', ['n' => $r->waiting]) }}</td><td></td>
                    </tr>
                @endforeach
                <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="9" class="text-center text-muted py-6">{{ __('pms.queue.empty') }}</td></tr>
                </tbody>
            </table>
        </section>
        <aside class="w-36 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @can('permission', 'reservations.checkin')
                <form method="POST" data-confirm="{{ __('pms.res.check_in_confirm') }}">@csrf
                    <button class="btn btn-side w-full" data-needs-selection="queue-grid" data-action="checkin" data-hotkey="alt+g">{{ __('pms.res.check_in') }} <span class="kbd ml-auto">Alt+G</span></button></form>
            @endcan
            @can('permission', 'reservations.edit')
                <a class="btn btn-side" data-needs-selection="queue-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.queue.assign_room') }} <span class="kbd ml-auto">Alt+E</span></a>
            @endcan
            <form method="POST">@csrf @method('DELETE')
                <button class="btn btn-side w-full" data-needs-selection="queue-grid" data-action="remove" data-hotkey="alt+d delete">{{ __('pms.queue.remove') }} <span class="kbd ml-auto">Alt+D</span></button></form>
            <a href="{{ route('housekeeping.index', ['f' => 'vd']) }}" class="btn btn-side">{{ __('pms.hkscreen.title') }}</a>
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
<script>setTimeout(() => location.reload(), 60000);   // dakikada bir yenilenir: oda hazır olunca görünsün</script>
@endsection
