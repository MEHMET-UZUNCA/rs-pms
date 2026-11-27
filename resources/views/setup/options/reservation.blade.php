@extends('layouts.app')
@section('title', __('pms.options.title'))
@section('content')
@php $v = fn (string $key) => old($key, $options[$key]); @endphp
<form method="POST" action="{{ route('setup.options.reservation.update') }}" class="win flex-1">
    @csrf @method('PUT')
    <div class="win-title"><span>{{ __('pms.common.setup_title') }} — {{ __('pms.options.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.options.subtitle') }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex flex-1 min-h-0">
        <aside class="w-52 shrink-0 border-r border-line p-2 overflow-auto max-md:hidden">@include('setup._tree')</aside>
        <div class="flex-1 min-w-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-2">
            <section class="win !min-h-fit p-3 grid grid-cols-[minmax(0,1fr)_9rem] items-center gap-x-3 gap-y-2">
                @foreach (['check_out_time', 'check_in_time', 'day_use_check_out_time', 'day_use_check_in_time'] as $key)
                    <label class="field-label !mb-0" for="{{ $key }}">{{ __('pms.options.'.$key) }}</label>
                    <input id="{{ $key }}" name="{{ $key }}" type="time" value="{{ $v($key) }}" class="input" required>
                @endforeach
                @foreach (['status_after_check_in', 'status_after_check_out', 'status_after_audit_occupied', 'status_after_no_show'] as $key)
                    <label class="field-label !mb-0" for="{{ $key }}">{{ __('pms.options.'.$key) }}</label>
                    <select id="{{ $key }}" name="{{ $key }}" class="input">
                        @foreach (\App\Domain\Reservations\Support\ReservationOptions::ROOM_STATUSES as $status)
                            <option value="{{ $status }}" @selected($v($key) === $status)>{{ __('pms.options.status.'.$status) }}</option>
                        @endforeach
                    </select>
                @endforeach
                <label class="field-label !mb-0" for="dirty_room_check_in">{{ __('pms.options.dirty_room_check_in') }}</label>
                <select id="dirty_room_check_in" name="dirty_room_check_in" class="input">
                    @foreach (\App\Domain\Reservations\Support\ReservationOptions::DIRTY_CHECK_IN as $o)<option value="{{ $o }}" @selected($v('dirty_room_check_in') === $o)>{{ __('pms.options.dirty.'.$o) }}</option>@endforeach
                </select>
            </section>
            <section class="win !min-h-fit p-3 grid grid-cols-[minmax(0,1fr)_14rem] items-center gap-x-3 gap-y-2 content-start">
                <b class="col-span-2">{{ __('pms.options.web_title') }}</b>
                <p class="col-span-2 text-[12.5px] text-muted">{{ __('pms.options.web_hint') }}</p>
                @foreach (['web_reservation_type' => $resTypes, 'web_market' => $markets, 'web_source' => $sources] as $key => $list)
                    <label class="field-label !mb-0" for="{{ $key }}">{{ __('pms.options.'.$key) }}</label>
                    <select id="{{ $key }}" name="{{ $key }}" class="input"><option value="">—</option>
                        @foreach ($list as $c)<option value="{{ $c->code }}" @selected($v($key) === $c->code)>{{ $c->code }} {{ $c->name }}</option>@endforeach</select>
                @endforeach
                <label class="field-label !mb-0" for="web_status">{{ __('pms.options.web_status') }}</label>
                <select id="web_status" name="web_status" class="input">@foreach (['confirmed', 'tentative'] as $st)<option value="{{ $st }}" @selected($v('web_status') === $st)>{{ __('pms.res.statuses.'.$st) }}</option>@endforeach</select>
            </section>
            <section class="win !min-h-fit p-3 flex flex-col gap-2">
                @foreach (['queue_rooms', 'meal_plan'] as $flag)
                    <label class="flex items-center gap-2"><input type="hidden" name="{{ $flag }}" value="0"><input type="checkbox" name="{{ $flag }}" value="1" @checked($v($flag))> {{ __('pms.options.'.$flag) }}</label>
                @endforeach
                <b class="mt-2">{{ __('pms.options.meal_defaults') }}</b>
                <table class="grid-table">
                    <thead><tr><th></th>@foreach (['breakfast', 'lunch', 'dinner'] as $meal)<th class="text-center">{{ __('pms.options.meals.'.$meal) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach (['meals_first_day', 'meals_departure_day', 'meals_day_use'] as $group)
                        <tr><td>{{ __('pms.options.'.$group) }}</td>
                            @foreach (['breakfast', 'lunch', 'dinner'] as $meal)
                                <td class="text-center"><input type="checkbox" name="{{ $group }}[{{ $meal }}]" value="1" @checked(old("{$group}.{$meal}", $options[$group][$meal] ?? false)) aria-label="{{ __('pms.options.'.$group) }} {{ __('pms.options.meals.'.$meal) }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-[12px] text-muted">{{ __('pms.options.meal_hint') }}</p>
            </section>
        </div>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('home') }}" class="btn" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
