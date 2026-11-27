@extends('layouts.app')
@section('title', $profile->exists ? $profile->display_name : __('pms.prof.new_'.$profile->type))
@section('content')
@php
    $v = fn (string $f) => old($f, $profile->{$f});
    $sel = fn (string $f, $id) => (string) old($f, $profile->{$f}) === (string) $id;
    $a = fn (string $f) => old('address.'.$f, $address?->{$f});
    $individual = $profile->type === 'individual';
    $countryName = app()->getLocale() === 'tr' ? 'name_tr' : 'name_en';
@endphp
<form method="POST" action="{{ $profile->exists ? route('profiles.update', $profile) : route('profiles.store', $profile->type) }}" class="win flex-1">
    @csrf
    @if ($profile->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $profile->exists ? $profile->display_name : __('pms.prof.new_'.$profile->type) }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.prof.types.'.$profile->type) }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-2">
        <section class="win !min-h-fit p-3 grid grid-cols-2 gap-x-3 gap-y-2 content-start">
            <b class="col-span-2">{{ __('pms.prof.identity') }}</b>
            @if ($individual)
                <div><label class="field-label" for="last_name">{{ __('pms.prof.last_name') }} *</label><input id="last_name" name="last_name" value="{{ $v('last_name') }}" maxlength="100" class="input @error('last_name') input-error @enderror" required autofocus></div>
                <div><label class="field-label" for="first_name">{{ __('pms.prof.first_name') }}</label><input id="first_name" name="first_name" value="{{ $v('first_name') }}" maxlength="100" class="input"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="title">{{ __('pms.prof.title_field') }}</label><input id="title" name="title" value="{{ $v('title') }}" maxlength="20" class="input" placeholder="Bay / Mr."></div>
                    <div><label class="field-label" for="gender">{{ __('pms.prof.gender') }}</label>
                        <select id="gender" name="gender" class="input"><option value=""></option>@foreach (['male', 'female', 'other'] as $g)<option value="{{ $g }}" @selected($v('gender') === $g)>{{ __('pms.prof.genders.'.$g) }}</option>@endforeach</select></div>
                </div>
                <div><label class="field-label" for="salutation">{{ __('pms.prof.salutation') }}</label><input id="salutation" name="salutation" value="{{ $v('salutation') }}" maxlength="30" class="input" placeholder="Dear Mr. Smith"></div>
                <div><label class="field-label" for="birth_date">{{ __('pms.prof.birth_date') }}</label><input id="birth_date" name="birth_date" value="{{ old('birth_date', $profile->birth_date?->format('d.m.Y')) }}" class="input @error('birth_date') input-error @enderror" autocomplete="off" placeholder="gg.aa.yyyy"></div>
                <div><label class="field-label" for="occupation">{{ __('pms.prof.occupation') }}</label><input id="occupation" name="occupation" value="{{ $v('occupation') }}" maxlength="100" class="input"></div>
                <div><label class="field-label" for="nationality_code">{{ __('pms.prof.nationality') }}</label>
                    <select id="nationality_code" name="nationality_code" class="input"><option value=""></option>@foreach ($countries as $c)<option value="{{ $c->code }}" @selected($v('nationality_code') === $c->code)>{{ $c->{$countryName} }}</option>@endforeach</select></div>
                <div><label class="field-label" for="language_code">{{ __('pms.prof.language') }}</label>
                    <select id="language_code" name="language_code" class="input"><option value=""></option>@foreach (['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'he' => 'עברית', 'ar' => 'العربية'] as $code => $label)<option value="{{ $code }}" @selected($v('language_code') === $code)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="field-label" for="tax_number">{{ __('pms.prof.id_number') }}</label><input id="tax_number" name="tax_number" value="{{ $v('tax_number') }}" maxlength="30" class="input"></div>
                <div><label class="field-label" for="vip_level_id">VIP</label>
                    <select id="vip_level_id" name="vip_level_id" class="input"><option value=""></option>@foreach ($vips as $x)<option value="{{ $x->id }}" @selected($sel('vip_level_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
            @else
                <div class="col-span-2"><label class="field-label" for="company_name">{{ __('pms.prof.company_name') }} *</label><input id="company_name" name="company_name" value="{{ $v('company_name') }}" maxlength="200" class="input @error('company_name') input-error @enderror" required autofocus></div>
                <div><label class="field-label" for="tax_office">{{ __('pms.prof.tax_office') }}</label><input id="tax_office" name="tax_office" value="{{ $v('tax_office') }}" maxlength="100" class="input"></div>
                <div><label class="field-label" for="tax_number">{{ __('pms.prof.tax_number') }}</label><input id="tax_number" name="tax_number" value="{{ $v('tax_number') }}" maxlength="30" class="input"></div>
                <div><label class="field-label" for="iata_code">IATA</label><input id="iata_code" name="iata_code" value="{{ $v('iata_code') }}" maxlength="20" class="input"></div>
                <div><label class="field-label" for="commission_pct">{{ __('pms.prof.commission') }} %</label><input id="commission_pct" name="commission_pct" value="{{ old('commission_pct', $profile->commission_pct !== null ? (float) $profile->commission_pct : '') }}" class="input text-right" inputmode="decimal"></div>
                <div><label class="field-label" for="default_market_segment_id">{{ __('pms.rates.market') }}</label>
                    <select id="default_market_segment_id" name="default_market_segment_id" class="input"><option value=""></option>@foreach ($segments as $x)<option value="{{ $x->id }}" @selected($sel('default_market_segment_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
                <div><label class="field-label" for="default_source_code_id">{{ __('pms.rates.source') }}</label>
                    <select id="default_source_code_id" name="default_source_code_id" class="input"><option value=""></option>@foreach ($sources as $x)<option value="{{ $x->id }}" @selected($sel('default_source_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
                <div class="col-span-2"><label class="field-label" for="default_rate_code_id">{{ __('pms.prof.default_rate') }}</label>
                    <select id="default_rate_code_id" name="default_rate_code_id" class="input"><option value=""></option>@foreach ($rates as $x)<option value="{{ $x->id }}" @selected($sel('default_rate_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
                <div class="col-span-2"><label class="field-label" for="language_code">{{ __('pms.prof.language') }}</label>
                    <select id="language_code" name="language_code" class="input"><option value=""></option>@foreach (['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский'] as $code => $label)<option value="{{ $code }}" @selected($v('language_code') === $code)>{{ $label }}</option>@endforeach</select></div>
            @endif
        </section>

        <section class="win !min-h-fit p-3 grid grid-cols-2 gap-x-3 gap-y-2 content-start">
            <b class="col-span-2">{{ __('pms.prof.contact') }}</b>
            <div><label class="field-label" for="mobile">{{ __('pms.prof.mobile') }}</label><input id="mobile" name="mobile" value="{{ old('mobile', $comm('mobile')) }}" maxlength="40" class="input" inputmode="tel"></div>
            <div><label class="field-label" for="phone">{{ __('pms.prof.phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $comm('phone')) }}" maxlength="40" class="input" inputmode="tel"></div>
            <div class="col-span-2"><label class="field-label" for="email">{{ __('pms.users.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $comm('email')) }}" maxlength="190" class="input @error('email') input-error @enderror"></div>
            <div class="col-span-2"><label class="field-label" for="addr1">{{ __('pms.prof.address') }}</label><input id="addr1" name="address[line1]" value="{{ $a('line1') }}" maxlength="200" class="input"></div>
            <div class="col-span-2"><input name="address[line2]" value="{{ $a('line2') }}" maxlength="200" class="input" aria-label="{{ __('pms.prof.address') }} 2"></div>
            <div class="grid grid-cols-[6rem_1fr] gap-2"><input name="address[postal_code]" value="{{ $a('postal_code') }}" maxlength="15" class="input" placeholder="{{ __('pms.prof.zip') }}" aria-label="{{ __('pms.prof.zip') }}">
                <input name="address[city]" value="{{ $a('city') }}" maxlength="100" class="input" placeholder="{{ __('pms.prof.city') }}" aria-label="{{ __('pms.prof.city') }}"></div>
            <div><input name="address[state]" value="{{ $a('state') }}" maxlength="60" class="input" placeholder="{{ __('pms.prof.state') }}" aria-label="{{ __('pms.prof.state') }}"></div>
            <div class="col-span-2"><select name="address[country_code]" class="input" aria-label="{{ __('pms.prof.country') }}"><option value="">{{ __('pms.prof.country') }}</option>@foreach ($countries as $c)<option value="{{ $c->code }}" @selected($a('country_code') === $c->code)>{{ $c->{$countryName} }}</option>@endforeach</select></div>

            <b class="col-span-2 mt-2">{{ __('pms.prof.privacy') }}</b>
            @foreach (['marketing_consent', 'no_mailing', 'no_emailing', 'store_private_data'] as $flag)
                <label class="flex items-center gap-2"><input type="hidden" name="{{ $flag }}" value="0"><input type="checkbox" name="{{ $flag }}" value="1" @checked($v($flag))> {{ __('pms.prof.'.$flag) }}</label>
            @endforeach
            <div><label class="field-label" for="status">{{ __('pms.res.status') }}</label>
                <select id="status" name="status" class="input">@foreach (['active', 'inactive', 'blacklisted'] as $s)<option value="{{ $s }}" @selected(($v('status') ?? 'active') === $s)>{{ __('pms.prof.statuses.'.$s) }}</option>@endforeach</select></div>
            <div><label class="field-label" for="blacklist_reason">{{ __('pms.prof.blacklist_reason') }}</label><input id="blacklist_reason" name="blacklist_reason" value="{{ $v('blacklist_reason') }}" maxlength="500" class="input @error('blacklist_reason') input-error @enderror"></div>
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('profiles.index', ['tur' => $profile->type, 'secili' => $profile->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
