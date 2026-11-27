@php $open = in_array($trace->status, ['open', 'in_progress'], true); @endphp
<div class="p-3 grid gap-3 lg:grid-cols-[2fr_3fr]">
    <div class="flex flex-col gap-2 min-w-0">
        <div><b>{{ __('pms.req.kinds.'.$trace->kind) }}</b> · {{ __('pms.req.departments.'.$trace->department) }}@if ($room) · {{ __('pms.card.room') }} {{ $room }}@endif @if ($trace->guest_name) · {{ $trace->guest_name }}@endif</div>
        <p class="whitespace-pre-line">{{ $trace->message }}</p>
        <form method="POST" action="{{ route('requests.status', $trace->id) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <input name="note" maxlength="1000" class="input !w-64 flex-1" placeholder="{{ __('pms.req.note_hint') }}" aria-label="{{ __('pms.req.note') }}">
            @if ($trace->status === 'open')<button name="status" value="in_progress" class="btn">{{ __('pms.req.start') }}</button>@endif
            @if ($open)
                <button name="status" value="done" class="btn btn-primary">{{ __('pms.req.complete') }}</button>
                <button name="status" value="cancelled" class="btn">{{ __('pms.req.cancel') }}</button>
            @else
                <button name="status" value="open" class="btn">{{ __('pms.req.reopen') }}</button>
            @endif
        </form>
        <form method="POST" action="{{ route('requests.note', $trace->id) }}" class="flex gap-2">
            @csrf
            <input name="note" maxlength="1000" class="input flex-1" required placeholder="{{ __('pms.req.add_note') }}" aria-label="{{ __('pms.req.add_note') }}">
            <button class="btn">{{ __('pms.req.note') }}</button>
        </form>
    </div>
    <table class="grid-table min-w-0 self-start">
        <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.req.by') }}</th><th>{{ __('pms.res.status') }}</th><th class="w-full">{{ __('pms.req.note') }}</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr><td class="tabular-nums">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('d.m H:i') }}</td><td>{{ $l->username }}</td>
                <td>{{ $l->status ? __('pms.req.statuses.'.$l->status) : '' }}</td><td class="whitespace-normal">{{ $l->note }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
