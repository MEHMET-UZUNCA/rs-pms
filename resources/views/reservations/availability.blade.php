@extends('layouts.app')
@section('title', __('pms.avail.title'))
@php
    $q = fn (array $over) => route('availability.index', array_merge(['bas' => $start->format('d.m.Y'), 'gun' => $span], $over));
    $canEdit = Gate::allows('permission', 'reservations.edit');
@endphp
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.avail.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.avail.subtitle') }}</span></div>
    <form method="GET" class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-line">
        <a href="{{ $q(['bas' => $start->subDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowleft">← {{ __('pms.rack.prev') }}</a>
        <a href="{{ $q(['bas' => 't']) }}" class="btn" data-hotkey="alt+home">{{ __('pms.rack.today') }}</a>
        <a href="{{ $q(['bas' => $start->addDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowright">{{ __('pms.rack.next') }} →</a>
        <label class="flex items-center gap-1.5 ml-2">{{ __('pms.rack.start') }} <input name="bas" value="{{ $start->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <input type="hidden" name="gun" value="{{ $span }}">
        <div class="ml-auto flex gap-1">
            @foreach (\App\Http\Controllers\Reservations\AvailabilityController::SPANS as $n)
                <a href="{{ $q(['gun' => $n]) }}" @class(['btn', 'btn-primary' => $n === $span])>{{ __('pms.rack.days', ['n' => $n]) }}</a>
            @endforeach
        </div>
    </form>
    <div class="flex-1 min-h-0 overflow-auto">
        <table class="grid-table text-[12px] min-w-max" data-availability>
            <thead class="sticky top-0 z-10 bg-surface">
                <tr><th class="sticky left-0 bg-surface z-10">{{ __('pms.res.room_type') }}</th>
                    @foreach ($days as $d)
                        <th @class(['text-center whitespace-nowrap', 'bg-amber-100' => $d['date']->isSameDay($bd), 'bg-brand-50' => $d['date']->isWeekend() && ! $d['date']->isSameDay($bd)])>{{ __('pms.rack.weekdays')[$d['date']->dayOfWeekIso - 1] }}<br>{{ $d['date']->format('d.m') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach ($types as $t)
                <tr><td class="sticky left-0 bg-panel whitespace-nowrap"><b>{{ $t->code }}</b> <span class="text-muted">{{ $t->name }}</span></td>
                    @foreach ($days as $d)
                        @php $c = $rows[$t->id][$d['key']] ?? null; $a = $c?->available ?? 0; @endphp
                        <td @class(['text-center tabular-nums p-0', 'text-danger font-semibold' => $a <= 0, 'text-warning' => $a > 0 && $a <= 2])
                            title="{{ $c ? __('pms.avail.cell', ['physical' => $c->physical_rooms, 'ooo' => $c->out_of_order, 'sold' => $c->sold_definite, 'tentative' => $c->sold_tentative, 'blocks' => $c->block_remaining_definite + $c->block_remaining_tentative]) : '' }}">
                            @if ($canEdit && $a > 0 && $d['date']->gte($bd))
                                <a class="block px-2 py-1 hover:bg-brand-100" data-type="{{ $t->code }}" href="{{ route('reservations.create', ['tip' => $t->id, 'gelis' => $d['date']->format('d.m.Y'), 'cikis' => $d['date']->addDay()->format('d.m.Y')]) }}">{{ $a }}</a>
                            @else
                                <span class="block px-2 py-1" data-type="{{ $t->code }}">{{ $a }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
            <tfoot class="sticky bottom-0 bg-surface font-semibold">
                @foreach (['available' => 'avail', 'sold' => 'sold', 'tentative' => 'tentative', 'blocks' => 'blocks', 'ooo' => 'ooo', 'pct' => 'occupancy'] as $field => $label)
                    <tr><td class="sticky left-0 bg-surface whitespace-nowrap">{{ __('pms.avail.rows.'.$label) }}</td>
                        @foreach ($days as $d)<td class="text-center tabular-nums" data-total="{{ $field }}">{{ $field === 'pct' ? number_format($d['pct'], 1, ',', '.') : ($d[$field] ?: ($field === 'available' ? 0 : '')) }}</td>@endforeach</tr>
                @endforeach
            </tfoot>
        </table>
    </div>
    <div class="flex items-center gap-3 px-3 py-1.5 border-t border-line text-[12px]">
        <span class="text-muted">{{ __('pms.avail.hint') }}</span>
        <a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</div>
@endsection
