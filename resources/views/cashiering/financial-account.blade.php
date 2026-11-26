@extends('layouts.app')
@section('title', $folio->exists ? __('pms.fa.edit_title', ['number' => $folio->folio_number]) : __('pms.fa.new_title'))
@section('content')
@php
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : '';
    $sel = fn (string $field) => (string) old($field, $folio->{$field});
@endphp
<form method="POST" action="{{ $folio->exists ? route('accounts.update', $folio) : route('accounts.store') }}" class="win flex-1">
    @csrf
    @if ($folio->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $folio->exists ? __('pms.fa.edit_title', ['number' => $folio->folio_number]) : __('pms.fa.new_title') }}</span>
        <span class="font-normal text-brand-100 text-[12px]">{{ __('pms.fa.subtitle') }}</span></div>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="p-3 grid gap-2.5 max-w-2xl">
        <div class="grid grid-cols-[10rem_1fr] items-center gap-x-3 gap-y-2.5">
            <label class="field-label !mb-0" for="folio_group_id">{{ __('pms.fa.group') }}</label>
            <select id="folio_group_id" name="folio_group_id" class="input">
                <option value=""></option>
                @foreach ($groups as $g)<option value="{{ $g->id }}" @selected($sel('folio_group_id') === (string) $g->id)>{{ $g->code }} — {{ $g->name }}</option>@endforeach
            </select>

            <span class="field-label !mb-0">{{ __('pms.fa.date_range') }}</span>
            <div class="flex items-center gap-2">
                <input name="valid_from" value="{{ old('valid_from', $date($folio->valid_from)) }}" class="input w-32 @error('valid_from') input-error @enderror" placeholder="t" aria-label="{{ __('pms.fa.valid_from') }}" autocomplete="off">
                <span>–</span>
                <input name="valid_until" value="{{ old('valid_until', $date($folio->valid_until)) }}" class="input w-32 @error('valid_until') input-error @enderror" placeholder="+7" aria-label="{{ __('pms.fa.valid_until') }}" autocomplete="off">
                <span class="text-muted text-[12px]">{{ __('pms.res.date_hint') }}</span>
            </div>

            <label class="field-label !mb-0" for="payee_profile_id">{{ __('pms.fa.profile') }}</label>
            <select id="payee_profile_id" name="payee_profile_id" class="input">
                <option value=""></option>
                @foreach ($profiles as $pr)<option value="{{ $pr->id }}" @selected($sel('payee_profile_id') === (string) $pr->id)>{{ $pr->display_name }}</option>@endforeach
            </select>

            <label class="field-label !mb-0" for="folio_number">{{ __('pms.fa.number') }}</label>
            <div class="flex items-center gap-2">
                <input id="folio_number" name="folio_number" value="{{ old('folio_number', $folio->folio_number) }}" maxlength="20" class="input w-40 @error('folio_number') input-error @enderror" autocomplete="off">
                @unless ($folio->exists)<span class="text-muted text-[12px]">{{ __('pms.fa.number_hint') }}</span>@endunless
            </div>

            <label class="field-label !mb-0" for="name">{{ __('pms.common.description') }} *</label>
            <input id="name" name="name" value="{{ old('name', $folio->name) }}" maxlength="100" class="input @error('name') input-error @enderror" required autofocus>

            <label class="field-label !mb-0" for="market_segment_id">{{ __('pms.fa.market') }}</label>
            <select id="market_segment_id" name="market_segment_id" class="input">
                <option value=""></option>
                @foreach ($segments as $s)<option value="{{ $s->id }}" @selected($sel('market_segment_id') === (string) $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach
            </select>

            <label class="field-label !mb-0" for="credit_limit">{{ __('pms.fa.credit_limit') }}</label>
            <input id="credit_limit" name="credit_limit" value="{{ old('credit_limit', $folio->credit_limit !== null ? number_format((float) $folio->credit_limit, 2, ',', '.') : '') }}" class="input w-40 text-right @error('credit_limit') input-error @enderror" inputmode="decimal">

            <label class="field-label !mb-0" for="source_code_id">{{ __('pms.fa.source') }}</label>
            <select id="source_code_id" name="source_code_id" class="input">
                <option value=""></option>
                @foreach ($sources as $s)<option value="{{ $s->id }}" @selected($sel('source_code_id') === (string) $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach
            </select>

            <label class="field-label !mb-0" for="currency_code">{{ __('pms.fa.currency') }}</label>
            <select id="currency_code" name="currency_code" class="input w-64">
                @foreach ($currencies as $c)<option value="{{ $c->code }}" @selected($sel('currency_code') === $c->code)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>

            <label class="field-label !mb-0" for="billing_info">{{ __('pms.fa.billing_info') }}</label>
            <input id="billing_info" name="billing_info" value="{{ old('billing_info', $folio->billing_info) }}" maxlength="500" class="input">

            <label class="field-label !mb-0" for="notes">{{ __('pms.fa.remarks') }}</label>
            <input id="notes" name="notes" value="{{ old('notes', $folio->notes) }}" maxlength="500" class="input">

            <span></span>
            <label class="flex items-center gap-2"><input type="hidden" name="no_post" value="0"><input type="checkbox" name="no_post" value="1" @checked(old('no_post', $folio->no_post))> {{ __('pms.fa.no_post') }}
                <span class="text-muted text-[12px]">{{ __('pms.fa.no_post_hint') }}</span></label>
        </div>
    </section>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line mt-auto">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ $folio->exists ? route('billing.account', $folio) : route('reservations.navigator', ['f' => 'fa']) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
