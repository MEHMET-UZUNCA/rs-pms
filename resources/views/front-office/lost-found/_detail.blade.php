<div class="p-3 text-[13px] grid gap-2" data-lf-item="{{ $item->number }}">
    <div class="flex flex-wrap gap-x-4 gap-y-1">
        <b>{{ $item->number }}</b><span>{{ $item->description }}</span>
        <span class="text-muted">{{ __('pms.lf.statuses.'.$item->status) }}</span>
        @if ($item->match)<span>↔ <b>{{ $item->match->number }}</b> {{ \Illuminate\Support\Str::limit($item->match->description, 40) }}</span>@endif
    </div>
    <div class="flex flex-wrap gap-x-4 text-[12px] text-muted">
        @if ($item->person_name)<span>{{ __('pms.lf.'.$item->kind.'_by') }}: {{ $item->person_name }}</span>@endif
        @if ($item->contact)<span>{{ $item->contact }}</span>@endif
        @if ($item->storage_location)<span>{{ __('pms.lf.storage') }}: {{ $item->storage_location }}</span>@endif
        @if ($item->returned_to)<span>{{ __('pms.lf.returned_to') }}: {{ $item->returned_to }}</span>@endif
    </div>
    @unless ($item->isResolved())
        <div class="flex flex-wrap gap-2 items-end">
            <form method="POST" action="{{ route('lost-found.resolve', $item) }}" class="flex gap-1 items-end" data-confirm="{{ __('pms.lf.return_confirm') }}">
                @csrf <input type="hidden" name="how" value="returned">
                <input name="to" class="input w-40" placeholder="{{ __('pms.lf.returned_to') }}" required>
                <button class="btn">{{ __('pms.lf.return') }}</button>
            </form>
            <form method="POST" action="{{ route('lost-found.resolve', $item) }}" data-confirm="{{ __('pms.lf.dispose_confirm') }}">@csrf <input type="hidden" name="how" value="disposed"><button class="btn">{{ __('pms.lf.dispose') }}</button></form>
            @if ($item->status === 'matched')
                <form method="POST" action="{{ route('lost-found.unmatch', $item) }}">@csrf<button class="btn">{{ __('pms.lf.unmatch') }}</button></form>
            @endif
            @unless ($item->matched_id)
                <form method="POST" action="{{ route('lost-found.destroy', $item) }}" data-confirm="{{ __('pms.common.delete_confirm') }}">@csrf @method('DELETE')<button class="btn">{{ __('pms.common.delete') }}</button></form>
            @endunless
        </div>
    @endunless
    <form method="POST" action="{{ route('lost-found.note', $item) }}" class="flex gap-1">
        @csrf <input name="note" class="input flex-1" placeholder="{{ __('pms.lf.add_note') }}" maxlength="500" required><button class="btn">{{ __('pms.lf.note') }}</button>
    </form>
    <table class="grid-table">
        <thead><tr><th>{{ __('pms.lf.action') }}</th><th>{{ __('pms.lf.entered') }}</th><th>{{ __('pms.common.user') }}</th><th>{{ __('pms.lf.note') }}</th></tr></thead>
        <tbody>
        @foreach ($actions as $a)
            <tr><td>{{ __('pms.lf.actions.'.$a->action) }}</td><td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d.m.Y H:i') }}</td><td>{{ $a->username }}</td><td>{{ $a->note }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
