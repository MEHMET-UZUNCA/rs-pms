@extends('layouts.app')
@php
    $new = ! $r->exists;
    $title = $new ? __('pms.card.new_title') : __('pms.card.edit_title', ['no' => $r->confirmation_number, 'name' => $r->profile?->display_name]);
    $v = fn (string $f, $default = null) => old($f, $r->{$f} ?? $default);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : '';
    $money = fn ($x) => number_format((float) $x, 2, ',', '.');
    $tabs = ['reservation' => '1', 'inventory' => '2', 'profiles' => '3', 'packages' => '4', 'rates' => '5', 'features' => '6',
        'credit_card' => '7', 'fixed_charges' => '8', 'notes' => '9', 'meal_plan' => '0'];
    $locked = in_array($r->status, ['cancelled', 'no_show', 'checked_out'], true);
@endphp
@section('title', $title)
@section('content')
<form method="POST" action="{{ $new ? route('reservations.store') : route('reservations.update', $r) }}" class="win flex-1" data-tabs>
    @csrf
    @unless ($new) @method('PUT') @endunless
    <div class="win-title">
        <span>{{ $title }}</span>
        @unless ($new)<span class="font-normal text-brand-100 text-[12px]">{{ __('pms.res.statuses.'.$r->status) }} · {{ $r->room?->number ?? __('pms.res.not_assigned') }}</span>@endunless
    </div>

    <nav class="flex flex-wrap gap-0.5 px-2 pt-2 border-b border-line" role="tablist">
        @foreach ($tabs as $tab => $key)
            <button type="button" role="tab" data-tab-button="{{ $tab }}" data-hotkey="ctrl+{{ $key }}"
                class="px-3 h-8 -mb-px border border-transparent rounded-t text-[13px] whitespace-nowrap aria-selected:bg-panel aria-selected:border-line aria-selected:border-b-panel aria-selected:font-semibold hover:bg-brand-50">
                {{ __('pms.card.tabs.'.$tab) }} <span class="text-[11px] text-muted">Ctrl+{{ $key }}</span>
            </button>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="flex-1 min-h-0 overflow-auto">
        {{-- Ctrl+1 Rezervasyon --}}
        <section data-tab-panel="reservation" class="grid gap-x-6 gap-y-3 p-3 lg:grid-cols-2">
            <fieldset class="border border-line rounded px-3 pb-3 flex flex-col gap-2">
                <legend class="px-1 text-[12px] text-muted">{{ __('pms.card.guest') }}</legend>
                @if ($new)
                    <input type="hidden" name="profile_id" value="{{ old('profile_id') }}" data-profile-id>
                    <div class="relative">
                        <label class="field-label" for="profile_search">{{ __('pms.card.search_profile') }}</label>
                        <input id="profile_search" class="input" autocomplete="off" data-profile-search="{{ route('reservations.profiles') }}" autofocus>
                        <div class="absolute z-20 left-0 right-0 bg-panel border border-line rounded shadow-lg hidden max-h-60 overflow-auto" data-profile-results></div>
                    </div>
                    <div class="text-[12px] font-semibold text-brand-700 hidden" data-profile-chosen></div>
                    <div class="text-[12px] text-muted" data-new-guest-label>{{ __('pms.card.new_guest') }}</div>
                    <div class="grid grid-cols-2 gap-2" data-new-guest>
                        <div><label class="field-label" for="last_name">{{ __('pms.card.last_name') }} *</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" class="input @error('last_name') input-error @enderror"></div>
                        <div><label class="field-label" for="first_name">{{ __('pms.card.first_name') }}</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" class="input"></div>
                        <div><label class="field-label" for="nationality_code">{{ __('pms.card.nationality') }}</label>
                            <select id="nationality_code" name="nationality_code" class="input"><option value="">—</option>
                                @foreach ($countries as $c)<option value="{{ $c->code }}" @selected(old('nationality_code') === $c->code)>{{ $c->code }} {{ app()->getLocale() === 'tr' ? $c->name_tr : $c->name_en }}</option>@endforeach
                            </select></div>
                        <div><label class="field-label" for="phone">{{ __('pms.card.phone') }}</label><input id="phone" name="phone" value="{{ old('phone') }}" class="input"></div>
                        <div class="col-span-2"><label class="field-label" for="email">{{ __('pms.card.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="input"></div>
                    </div>
                @else
                    <div class="text-[15px] font-semibold">{{ $r->profile?->display_name }} <span class="text-muted font-normal">{{ $r->profile?->nationality_code }}</span></div>
                    <div class="text-[12px] text-muted">{{ $r->profile?->communications->pluck('value')->implode(' · ') }}</div>
                @endif
            </fieldset>

            <fieldset class="border border-line rounded px-3 pb-3 grid grid-cols-2 md:grid-cols-4 gap-2 content-start" @disabled($locked)>
                <legend class="px-1 text-[12px] text-muted">{{ __('pms.card.stay') }}</legend>
                <div><label class="field-label" for="arrival_date">{{ __('pms.res.arrival') }} *</label>
                    <input id="arrival_date" name="arrival_date" value="{{ old('arrival_date', $date($r->arrival_date)) }}" class="input @error('arrival_date') input-error @enderror" required data-date></div>
                <div><label class="field-label" for="departure_date">{{ __('pms.res.departure') }} *</label>
                    <input id="departure_date" name="departure_date" value="{{ old('departure_date', $date($r->departure_date)) }}" class="input @error('departure_date') input-error @enderror" required data-date></div>
                <div><label class="field-label" for="adults">{{ __('pms.card.adults') }} *</label>
                    <input id="adults" name="adults" type="number" min="1" max="20" value="{{ $v('adults', 2) }}" class="input" required></div>
                <div><label class="field-label" for="children">{{ __('pms.card.children') }}</label>
                    <input id="children" name="children" type="number" min="0" max="20" value="{{ $v('children', 0) }}" class="input"></div>
                <div class="col-span-2"><label class="field-label" for="room_type_id">{{ __('pms.res.room_type') }} *</label>
                    <select id="room_type_id" name="room_type_id" class="input" required data-room-type>
                        @foreach ($roomTypes as $t)<option value="{{ $t->id }}" @selected((int) $v('room_type_id') === (int) $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach
                    </select></div>
                <div class="col-span-2"><label class="field-label" for="room_id">{{ __('pms.card.room') }}</label>
                    <select id="room_id" name="room_id" class="input" data-room-select>
                        <option value="">{{ __('pms.card.no_room') }}</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" data-type="{{ $room->room_type_id }}" @selected((int) $v('room_id') === (int) $room->id)>{{ $room->number }} · {{ __('pms.hk.fo_status.'.$room->fo_status) }} / {{ __('pms.hk.hk_status.'.$room->hk_status) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-span-2"><label class="field-label" for="rate_code_id">{{ __('pms.res.rate_code') }} *</label>
                    <select id="rate_code_id" name="rate_code_id" class="input" required>
                        @foreach ($rateCodes as $rc)<option value="{{ $rc->id }}" @selected((int) $v('rate_code_id') === (int) $rc->id)>{{ $rc->code }} — {{ $rc->name }}</option>@endforeach
                    </select></div>
                <div class="col-span-2"><label class="field-label" for="manual_amount">{{ __('pms.card.manual_amount') }}</label>
                    <input id="manual_amount" name="manual_amount" value="{{ old('manual_amount') }}" inputmode="decimal" class="input" placeholder="{{ __('pms.card.manual_hint') }}"></div>
            </fieldset>

            <fieldset class="border border-line rounded px-3 pb-3 grid grid-cols-2 gap-2 lg:col-span-2" @disabled($locked)>
                <legend class="px-1 text-[12px] text-muted">{{ __('pms.res.status') }}</legend>
                @foreach ([['reservation_type_id', 'reservation_type', $types], ['market_segment_id', 'market', $segments], ['source_code_id', 'source', $sources]] as [$field, $label, $options])
                    <div><label class="field-label" for="{{ $field }}">{{ __('pms.card.'.$label) }} *</label>
                        <select id="{{ $field }}" name="{{ $field }}" class="input" required>
                            @foreach ($options as $o)<option value="{{ $o->id }}" @selected((int) $v($field) === (int) $o->id)>{{ $o->code }} — {{ $o->name }}</option>@endforeach
                        </select></div>
                @endforeach
                @foreach ([['company_profile_id', 'company'], ['agent_profile_id', 'agent']] as [$field, $label])
                    <div><label class="field-label" for="{{ $field }}">{{ __('pms.card.'.$label) }}</label>
                        <select id="{{ $field }}" name="{{ $field }}" class="input"><option value="">{{ __('pms.common.none') }}</option>
                            @foreach ($firms as $f)<option value="{{ $f->id }}" @selected((int) $v($field) === (int) $f->id)>{{ $f->display_name }}</option>@endforeach
                        </select></div>
                @endforeach
                <div><label class="field-label" for="guarantee_type">{{ __('pms.card.guarantee') }}</label>
                    <select id="guarantee_type" name="guarantee_type" class="input">
                        @foreach (\App\Http\Controllers\Reservations\ReservationCardController::GUARANTEES as $g)<option value="{{ $g }}" @selected($v('guarantee_type', 'none') === $g)>{{ __('pms.card.guarantees.'.$g) }}</option>@endforeach
                    </select></div>
                <div><label class="field-label" for="credit_limit">{{ __('pms.card.credit_limit') }}</label>
                    <input id="credit_limit" name="credit_limit" value="{{ $v('credit_limit') }}" inputmode="decimal" class="input"></div>
                <label class="flex items-center gap-2 col-span-2"><input type="hidden" name="room_charges_allowed" value="0"><input type="checkbox" name="room_charges_allowed" value="1" @checked($v('room_charges_allowed', true))> {{ __('pms.card.room_charges') }}</label>
                @unless ($new)
                    <div class="col-span-2 text-[13px]"><span class="text-muted">{{ __('pms.card.nights_total') }}:</span> <b>{{ $money($r->total_room_amount) }} {{ $r->currency_code }}</b>
                        · <span class="text-muted">{{ __('pms.common.balance') }}:</span> <b>{{ $money($r->folios->sum('balance')) }}</b></div>
                @endunless
            </fieldset>
        </section>

        {{-- Ctrl+3 Profiller --}}
        <section data-tab-panel="profiles" class="p-3 flex flex-col gap-2" hidden>
            @if ($r->profile)<div class="win p-3"><b>{{ __('pms.card.guest') }}:</b> {{ $r->profile->display_name }} · {{ $r->profile->nationality_code }} · {{ $r->profile->communications?->pluck('value')->implode(' · ') }}</div>@endif
            @forelse ($linked as $p)
                <div class="win p-3"><b>{{ $p->id === $r->company_profile_id ? __('pms.card.company') : __('pms.card.agent') }}:</b> {{ $p->display_name }}</div>
            @empty
                <p class="text-muted">{{ __('pms.card.no_profiles') }}</p>
            @endforelse
        </section>

        {{-- Ctrl+5 Gece fiyatları --}}
        <section data-tab-panel="rates" class="p-3" hidden>
            @if ($new)
                <p class="text-muted">{{ __('pms.card.prices_after_save') }}</p>
            @else
                <table class="grid-table">
                    <thead><tr><th>{{ __('pms.card.stay_date') }}</th><th>{{ __('pms.res.room_type') }}</th><th>{{ __('pms.res.rate_code') }}</th><th class="text-right">{{ __('pms.card.amount') }}</th><th class="text-right">{{ __('pms.card.net') }}</th><th class="text-right">{{ __('pms.card.package') }}</th><th>{{ __('pms.card.overridden') }}</th></tr></thead>
                    <tbody>
                    @foreach ($r->nightRows->sortBy('stay_date') as $n)
                        <tr><td>{{ $n->stay_date->format('d.m.Y') }}</td><td>{{ $roomTypes->firstWhere('id', $n->charge_room_type_id)?->code }}</td><td>{{ $n->rateCode?->code }}</td>
                            <td class="text-right tabular-nums">{{ $money($n->amount) }}</td><td class="text-right tabular-nums">{{ $money($n->net_amount) }}</td>
                            <td class="text-right tabular-nums">{{ $money($n->package_amount) }}</td><td>{{ $n->is_overridden ? '✓' : '' }}</td></tr>
                    @endforeach
                    <tr class="font-semibold"><td colspan="3">{{ __('pms.card.total') }}</td><td class="text-right tabular-nums">{{ $money($r->nightRows->sum('amount')) }}</td><td colspan="3"></td></tr>
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Ctrl+9 Notlar --}}
        <section data-tab-panel="notes" class="p-3 grid gap-3 lg:grid-cols-2" hidden>
            <div><label class="field-label" for="guest_comments">{{ __('pms.card.guest_comments') }}</label>
                <textarea id="guest_comments" name="guest_comments" rows="6" class="input !h-auto py-1">{{ $v('guest_comments') }}</textarea></div>
            <div><label class="field-label" for="internal_notes">{{ __('pms.card.internal_notes') }}</label>
                <textarea id="internal_notes" name="internal_notes" rows="6" class="input !h-auto py-1">{{ $v('internal_notes') }}</textarea></div>
            @if ($history->isNotEmpty())
                <div class="lg:col-span-2"><div class="field-label">{{ __('pms.card.status_history') }}</div>
                    <table class="grid-table"><tbody>
                        @foreach ($history as $h)<tr><td>{{ \Illuminate\Support\Carbon::parse($h->created_at)->format('d.m.Y H:i') }}</td><td>{{ $h->from_status ? __('pms.res.statuses.'.$h->from_status) : '—' }} → {{ __('pms.res.statuses.'.$h->to_status) }}</td></tr>@endforeach
                    </tbody></table></div>
            @endif
        </section>

        @foreach (['inventory', 'packages', 'features', 'credit_card', 'fixed_charges', 'meal_plan'] as $tab)
            <section data-tab-panel="{{ $tab }}" class="p-3" hidden><p class="text-muted">{{ __('pms.card.not_yet') }}</p></section>
        @endforeach
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <span class="text-[12px] text-muted">{{ $r->confirmation_number }}</span>
        @unless ($locked)<button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.card.save') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>@endunless
        <a href="{{ route('reservations.navigator', $new ? [] : ['secili' => $r->id, 'no' => $r->confirmation_number]) }}" @class(['btn', 'ml-auto' => $locked]) data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
