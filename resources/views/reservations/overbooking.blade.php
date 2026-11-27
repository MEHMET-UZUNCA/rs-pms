@extends('layouts.app')
@section('title', __('pms.ob.title'))
@php $q = fn (array $over) => route('overbooking.index', array_merge(['bas' => $start->format('d.m.Y'), 'gun' => $span], $over)); @endphp
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.ob.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.ob.subtitle') }}</span></div>
    <div class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-line">
        <a href="{{ $q(['bas' => $start->subDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowleft">← {{ __('pms.rack.prev') }}</a>
        <a href="{{ $q(['bas' => 't']) }}" class="btn" data-hotkey="alt+home">{{ __('pms.rack.today') }}</a>
        <a href="{{ $q(['bas' => $start->addDays($span)->format('d.m.Y')]) }}" class="btn" data-hotkey="alt+arrowright">{{ __('pms.rack.next') }} →</a>
        <div class="ml-auto flex gap-1">@foreach (\App\Http\Controllers\Reservations\OverbookingController::SPANS as $n)<a href="{{ $q(['gun' => $n]) }}" @class(['btn', 'btn-primary' => $n === $span])>{{ __('pms.rack.days', ['n' => $n]) }}</a>@endforeach</div>
    </div>
    <form method="POST" action="{{ route('overbooking.save') }}" class="flex-1 min-h-0 flex flex-col">
        @csrf
        <div class="flex-1 min-h-0 overflow-auto">
            <table class="grid-table text-[12.5px] min-w-max" data-overbooking>
                <thead class="sticky top-0 z-10 bg-surface"><tr><th class="sticky left-0 bg-surface z-10">{{ __('pms.res.room_type') }}</th>
                    @foreach ($days as $d)<th @class(['text-center', 'bg-amber-100' => $d->isSameDay($bd), 'bg-brand-50' => $d->isWeekend() && ! $d->isSameDay($bd)])>{{ __('pms.rack.weekdays')[$d->dayOfWeekIso - 1] }}<br>{{ $d->format('d.m') }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($types as $t)
                    <tr><td class="sticky left-0 bg-panel whitespace-nowrap"><b>{{ $t->code }}</b> <span class="text-muted">{{ $t->name }}</span></td>
                        @foreach ($days as $d)
                            @php $c = $cells[$t->id][$d->toDateString()] ?? null; $avail = (int) ($c->available ?? 0); $limit = (int) ($c->overbooking_limit ?? 0); @endphp
                            <td class="p-1 text-center align-top">
                                <div @class(['tabular-nums text-[12px]', 'text-danger font-semibold' => $avail < 0, 'text-muted' => $avail >= 0]) title="{{ __('pms.ob.available') }}" data-avail="{{ $t->code }}-{{ $d->toDateString() }}">{{ $avail }}</div>
                                <input name="limit[{{ $t->id }}][{{ $d->toDateString() }}]" value="{{ $limit ?: '' }}" inputmode="numeric" maxlength="2" placeholder="0"
                                    @class(['input !h-7 w-12 text-center !px-1', '!border-danger' => $avail + $limit < 0, '!bg-amber-50' => $limit > 0]) aria-label="{{ $t->code }} {{ $d->format('d.m') }} {{ __('pms.ob.limit') }}">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-t border-line text-[12.5px]">
            <span class="text-muted">{{ __('pms.ob.hint') }}</span>
            <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
            <a href="{{ route('home') }}" class="btn" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
        </div>
    </form>
    <form method="POST" action="{{ route('overbooking.save') }}" class="flex flex-wrap items-end gap-2 px-3 py-2 border-t border-line bg-surface">
        @csrf
        <b class="w-full text-[12.5px]">{{ __('pms.ob.range') }}</b>
        <fieldset class="flex flex-wrap gap-x-3 gap-y-1"><legend class="sr-only">{{ __('pms.res.room_type') }}</legend>
            @foreach ($types as $t)<label class="flex items-center gap-1"><input type="checkbox" name="room_types[]" value="{{ $t->id }}"> {{ $t->code }}</label>@endforeach</fieldset>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.from') }}</span><input name="from" value="{{ $start->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.to') }}</span><input name="to" value="{{ $start->addDays(6)->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
        <label class="flex flex-col"><span class="field-label">{{ __('pms.ob.limit') }}</span><input name="value" value="1" type="number" min="0" max="99" class="input w-20"></label>
        <button class="btn">{{ __('pms.ob.apply') }}</button>
    </form>
</div>
@endsection
