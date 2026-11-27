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
                        <input id="profile_search" class="input" autocomplete="off" data-profile-search="{{ route('reservations.profiles') }}" data-blacklisted="{{ __('pms.prof.statuses.blacklisted') }}" autofocus>
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
                    @if ($r->block_id && ($blk = \App\Domain\Reservations\Models\Block::find($r->block_id)))
                        <div class="text-[12px]">{{ __('pms.block.types.'.$blk->type) }}: <a class="text-brand-600 hover:underline font-semibold" href="{{ route('blocks.show', $blk) }}">{{ $blk->code }} · {{ $blk->name }}</a></div>
                    @endif
                    @if ($r->profile?->status === 'blacklisted')
                        <div class="flash flash-error !py-1" role="alert"><b>{{ __('pms.prof.statuses.blacklisted') }}:</b> {{ $r->profile->blacklist_reason }}</div>
                    @endif
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
                @php $lateCo = $v('etd') && substr((string) $v('etd'), 0, 5) > $checkOutTime; @endphp
                <div><label class="field-label" for="eta">{{ __('pms.card.eta') }}</label>
                    <input id="eta" name="eta" type="time" value="{{ substr((string) $v('eta'), 0, 5) }}" class="input @error('eta') input-error @enderror"></div>
                <div><label class="field-label" for="etd">{{ __('pms.card.etd') }} @if ($lateCo)<span class="text-danger font-semibold">· {{ __('pms.card.late_co') }}</span>@endif</label>
                    <input id="etd" name="etd" type="time" value="{{ substr((string) $v('etd'), 0, 5) }}" class="input @error('etd') input-error @enderror" title="{{ __('pms.card.etd_hint', ['time' => $checkOutTime]) }}"></div>
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

        <section data-tab-panel="meal_plan" class="p-3 flex flex-col gap-3" hidden>
            @if ($new)
                <p class="text-muted">{{ __('pms.meal.save_first') }}</p>
            @else
                @php $nights = $r->nightRows->where('status', '!=', 'cancelled')->sortBy('stay_date'); @endphp
                <table class="grid-table max-w-2xl" data-meal-plan>
                    <thead><tr><th>{{ __('pms.common.date') }}</th><th>{{ __('pms.res.rate_code') }}</th><th>{{ __('pms.rates.board') }}</th><th>{{ __('pms.meal.meals') }}</th></tr></thead>
                    <tbody>
                    @foreach ($nights as $n)
                        @php $b = $boards->firstWhere('id', $n->package_id); @endphp
                        <tr><td>{{ $n->stay_date->format('d.m.Y D') }}</td><td>{{ $n->rateCode?->code }}</td>
                            <td class="font-semibold">{{ $b ? $b->code.' - '.$b->name : __('pms.rates.no_board') }}</td>
                            <td class="text-[12px]">{{ $b ? collect(['breakfast', 'lunch', 'dinner'])->filter(fn ($m) => $b->{'includes_'.$m})->map(fn ($m) => __('pms.options.meals.'.$m))->implode(', ') : '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                @unless ($locked)
                    <div class="flex flex-wrap items-end gap-2 rounded border border-line p-2 max-w-2xl">
                        <b class="w-full">{{ __('pms.meal.change') }}</b>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span>
                            <select name="from" form="board-form" class="input">@foreach ($nights as $n)<option value="{{ $n->stay_date->toDateString() }}">{{ $n->stay_date->format('d.m.Y') }}</option>@endforeach</select></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.to') }}</span>
                            <select name="to" form="board-form" class="input">@foreach ($nights as $n)<option value="{{ $n->stay_date->toDateString() }}" @selected($loop->last)>{{ $n->stay_date->format('d.m.Y') }}</option>@endforeach</select></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.rates.board') }}</span>
                            <select name="package_id" form="board-form" class="input"><option value="">{{ __('pms.rates.no_board') }}</option>@foreach ($boards as $b)<option value="{{ $b->id }}">{{ $b->code }} - {{ $b->name }}</option>@endforeach</select></label>
                        <button form="board-form" class="btn btn-primary">{{ __('pms.meal.apply') }}</button>
                        <p class="w-full text-[12px] text-muted">{{ __('pms.meal.price_hint') }}</p>
                    </div>
                @endunless
            @endif
        </section>

        {{-- Ctrl+8 Fixed Charges: gece kapanışında kendiliğinden yazılan sabit ücretler --}}
        <section data-tab-panel="fixed_charges" class="p-3 flex flex-col gap-3" hidden>
            @if ($new)
                <p class="text-muted">{{ __('pms.meal.save_first') }}</p>
            @else
                <table class="grid-table max-w-5xl">
                    <thead><tr><th>{{ __('pms.fixed.code') }}</th><th>{{ __('pms.fixed.description') }}</th><th class="text-right">{{ __('pms.fixed.quantity') }}</th>
                        <th class="text-right">{{ __('pms.fixed.amount') }}</th><th class="text-right">{{ __('pms.fixed.total') }}</th><th>{{ __('pms.fixed.frequency') }}</th>
                        <th>{{ __('pms.fixed.from') }}</th><th>{{ __('pms.fixed.to') }}</th><th>{{ __('pms.fixed.window') }}</th><th>{{ __('pms.fixed.last_posted') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($fixedCharges as $fc)
                        <tr><td class="font-semibold">{{ $fc->code }} {{ $fc->code_name }}</td><td>{{ $fc->description }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format((float) $fc->quantity, 2, ',', '.'), '0'), ',') }}</td><td class="text-right">{{ $money($fc->amount) }}</td>
                            <td class="text-right font-semibold">{{ $money($fc->amount * $fc->quantity) }}</td><td>{{ __('pms.fixed.frequencies.'.$fc->frequency) }}</td>
                            <td>{{ $date($fc->date_from) }}</td><td>{{ $date($fc->date_to) }}</td><td>{{ $fc->folio_window }}</td><td>{{ $date($fc->last_posted_date) }}</td>
                            <td>@unless ($locked)<button form="fc-del-{{ $fc->id }}" class="btn !h-6 !px-2" title="{{ __('pms.common.delete') }}">✕</button>@endunless</td></tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">{{ __('pms.fixed.none') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @unless ($locked)
                    @php $lastNight = $r->departure_date->copy()->subDay(); $firstNight = $r->arrival_date->max(app(\App\Domain\Core\Models\Property::class)->current_business_date); @endphp
                    <div class="flex flex-wrap items-end gap-2 rounded border border-line p-2 max-w-5xl">
                        <b class="w-full">{{ __('pms.fixed.new') }}</b>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.code') }} *</span>
                            <select name="transaction_code_id" form="fixed-charge-form" class="input w-64" required data-fixed-code><option value=""></option>
                                @foreach ($chargeCodes as $c)<option value="{{ $c->id }}" data-price="{{ $c->default_price }}">{{ $c->code }} {{ $c->name }}</option>@endforeach</select></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.description') }}</span><input name="description" form="fixed-charge-form" maxlength="255" class="input w-48"></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.quantity') }}</span><input name="quantity" form="fixed-charge-form" value="1" inputmode="decimal" class="input w-16 text-right"></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.amount') }} *</span><input name="amount" form="fixed-charge-form" inputmode="decimal" class="input w-28 text-right" required data-fixed-amount></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.frequency') }}</span>
                            <select name="frequency" form="fixed-charge-form" class="input">@foreach (\App\Domain\Reservations\Services\FixedChargeService::FREQUENCIES as $f)<option value="{{ $f }}" @selected($f === 'daily')>{{ __('pms.fixed.frequencies.'.$f) }}</option>@endforeach</select></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.from') }}</span><input name="date_from" form="fixed-charge-form" value="{{ $firstNight->format('d.m.Y') }}" class="input w-28"></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.to') }}</span><input name="date_to" form="fixed-charge-form" value="{{ $lastNight->format('d.m.Y') }}" class="input w-28"></label>
                        <label class="flex flex-col"><span class="field-label">{{ __('pms.fixed.window') }}</span>
                            <select name="folio_window" form="fixed-charge-form" class="input w-16">@for ($w = 1; $w <= 8; $w++)<option value="{{ $w }}">{{ $w }}</option>@endfor</select></label>
                        <button form="fixed-charge-form" class="btn btn-primary">{{ __('pms.common.new') }}</button>
                        <p class="w-full text-[12px] text-muted">{{ __('pms.fixed.hint') }}</p>
                    </div>
                @endunless
            @endif
        </section>

        @foreach (['inventory', 'packages', 'features', 'credit_card'] as $tab)
            <section data-tab-panel="{{ $tab }}" class="p-3" hidden><p class="text-muted">{{ __('pms.card.not_yet') }}</p></section>
        @endforeach
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <span class="text-[12px] text-muted">{{ $r->confirmation_number }}</span>
        @unless ($new)<a href="{{ route('reservations.letter', $r) }}" class="btn" data-hotkey="alt+l">{{ __('pms.res.letter') }} <span class="kbd">Alt+L</span></a>@endunless
        @unless ($locked)<button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.card.save') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>@endunless
        <a href="{{ route('reservations.navigator', $new ? [] : ['secili' => $r->id, 'no' => $r->confirmation_number]) }}" @class(['btn', 'ml-auto' => $locked]) data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@unless ($new)
    <form method="POST" action="{{ route('reservations.board', $r) }}" id="board-form" hidden>@csrf</form>
    <form method="POST" action="{{ route('reservations.fixed-charges.store', $r) }}" id="fixed-charge-form" hidden>@csrf</form>
    @foreach ($fixedCharges as $fc)
        <form method="POST" action="{{ route('reservations.fixed-charges.destroy', [$r, $fc->id]) }}" id="fc-del-{{ $fc->id }}" hidden data-confirm="{{ __('pms.fixed.confirm_delete') }}">@csrf @method('DELETE')</form>
    @endforeach
@endunless
@endsection
