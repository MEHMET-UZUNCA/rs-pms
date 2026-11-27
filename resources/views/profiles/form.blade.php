@extends('layouts.app')
@section('title', $profile->exists ? $profile->display_name : __('pms.prof.new_'.$profile->type))
@section('content')
{{-- Fidelio profil kartı: yalnız otelin kullandığı alanlar; Fidelio'nun Profile ve More Fields sekmeleri tek sayfada. --}}
@php
    $v = fn (string $f) => old($f, $profile->{$f});
    $sel = fn (string $f, $id) => (string) old($f, $profile->{$f}) === (string) $id;
    $a = fn (string $f) => old('address.'.$f, $address?->{$f});
    $individual = $profile->type === 'individual';
    $countryName = app()->getLocale() === 'tr' ? 'name_tr' : 'name_en';
    $languages = ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'he' => 'עברית', 'ar' => 'العربية'];
    $docs = old('documents', $documents);
    $docTypes = \App\Domain\Profiles\Services\ProfileService::DOCUMENT_TYPES;
    $active = old('active', $profile->status !== 'inactive' ? '1' : '0') === '1';
@endphp
<form method="POST" action="{{ $profile->exists ? route('profiles.update', $profile) : route('profiles.store', $profile->type) }}" class="win flex-1">
    @csrf
    @if ($profile->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $profile->exists ? $profile->display_name : __('pms.prof.new_'.$profile->type) }}</span>
        <span class="font-normal text-brand-100 text-[12px]">{{ __('pms.prof.types.'.$profile->type) }}@if ($profile->exists) · ID {{ $profile->id }}@endif</span></div>

    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-[3fr_2fr]">
        <div class="grid gap-3 content-start">
            <section class="win !min-h-fit p-3 grid grid-cols-[9rem_1fr_auto_8rem] gap-x-2 gap-y-1.5 items-center">
                @if ($individual)
                    <label class="font-semibold" for="last_name">{{ __('pms.prof.last_name') }} *</label>
                    <input id="last_name" name="last_name" value="{{ $v('last_name') }}" maxlength="100" class="input col-span-3 @error('last_name') input-error @enderror" required autofocus>
                    <label for="first_name">{{ __('pms.prof.first_name') }}</label>
                    <input id="first_name" name="first_name" value="{{ $v('first_name') }}" maxlength="100" class="input col-span-3">
                @else
                    <label class="font-semibold" for="company_name">{{ __('pms.prof.company_name') }} *</label>
                    <input id="company_name" name="company_name" value="{{ $v('company_name') }}" maxlength="200" class="input col-span-3 @error('company_name') input-error @enderror" required autofocus>
                @endif
                @if ($individual)
                    <label for="position">{{ __('pms.prof.position') }}</label><input id="position" name="position" value="{{ $v('position') }}" maxlength="100" class="input col-span-3">
                    <label for="department">{{ __('pms.prof.department') }}</label><input id="department" name="department" value="{{ $v('department') }}" maxlength="100" class="input col-span-3">
                @endif
                <label for="language_code">{{ __('pms.prof.language') }}</label>
                <select id="language_code" name="language_code" class="input @if (! $individual) col-span-3 @endif"><option value=""></option>@foreach ($languages as $code => $label)<option value="{{ $code }}" @selected($v('language_code') === $code)>{{ $label }}</option>@endforeach</select>
                @if ($individual)
                    <label for="title" class="pl-2">{{ __('pms.prof.title_field') }}</label><input id="title" name="title" value="{{ $v('title') }}" maxlength="20" class="input" placeholder="Mr. / Bay">
                    <label for="salutation">{{ __('pms.prof.salutation') }}</label><input id="salutation" name="salutation" value="{{ $v('salutation') }}" maxlength="30" class="input col-span-3" placeholder="Mr. Smith">
                    <label for="letter_greeting">{{ __('pms.prof.letter_greeting') }}</label><input id="letter_greeting" name="letter_greeting" value="{{ $v('letter_greeting') }}" maxlength="60" class="input col-span-3" placeholder="Dear Mr. Smith">
                @else
                    <label for="tax_office">{{ __('pms.prof.tax_office') }}</label><input id="tax_office" name="tax_office" value="{{ $v('tax_office') }}" maxlength="100" class="input">
                    <label for="tax_number" class="pl-2">{{ __('pms.prof.tax_number') }}</label><input id="tax_number" name="tax_number" value="{{ $v('tax_number') }}" maxlength="30" class="input">
                    <label for="iata_code">IATA</label><input id="iata_code" name="iata_code" value="{{ $v('iata_code') }}" maxlength="20" class="input">
                    <label for="commission_pct" class="pl-2">{{ __('pms.prof.commission') }} %</label><input id="commission_pct" name="commission_pct" value="{{ old('commission_pct', $profile->commission_pct !== null ? (float) $profile->commission_pct : '') }}" class="input text-right" inputmode="decimal">
                @endif
            </section>

            <section class="win !min-h-fit p-3 grid grid-cols-2 gap-x-3 gap-y-1.5 content-start">
                <b class="col-span-2">{{ __('pms.prof.address') }}</b>
                <div class="col-span-2"><input id="addr1" name="address[line1]" value="{{ $a('line1') }}" maxlength="200" class="input" aria-label="{{ __('pms.prof.address') }}"></div>
                <div class="col-span-2"><input name="address[line2]" value="{{ $a('line2') }}" maxlength="200" class="input" aria-label="{{ __('pms.prof.address') }} 2"></div>
                <div class="grid grid-cols-[6rem_1fr] gap-2"><input name="address[postal_code]" value="{{ $a('postal_code') }}" maxlength="15" class="input" placeholder="{{ __('pms.prof.zip') }}" aria-label="{{ __('pms.prof.zip') }}">
                    <input name="address[city]" value="{{ $a('city') }}" maxlength="100" class="input" placeholder="{{ __('pms.prof.city') }}" aria-label="{{ __('pms.prof.city') }}"></div>
                <div><input name="address[state]" value="{{ $a('state') }}" maxlength="60" class="input" placeholder="{{ __('pms.prof.state') }}" aria-label="{{ __('pms.prof.state') }}"></div>
                <div class="col-span-2"><select name="address[country_code]" class="input" aria-label="{{ __('pms.prof.country') }}"><option value="">{{ __('pms.prof.country') }}</option>@foreach ($countries as $c)<option value="{{ $c->code }}" @selected($a('country_code') === $c->code)>{{ $c->{$countryName} }}</option>@endforeach</select></div>
            </section>

            <section class="win !min-h-fit p-3 grid grid-cols-[9rem_1fr] gap-x-2 gap-y-1.5 items-center">
                <b class="col-span-2">{{ __('pms.prof.communication') }}</b>
                <label for="mobile">{{ __('pms.prof.mobile') }}</label><input id="mobile" name="mobile" value="{{ old('mobile', $comm('mobile')) }}" maxlength="40" class="input" inputmode="tel">
                <label for="phone">{{ __('pms.prof.phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $comm('phone')) }}" maxlength="40" class="input" inputmode="tel">
                <label for="email">{{ __('pms.users.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $comm('email')) }}" maxlength="190" class="input @error('email') input-error @enderror">
            </section>
        </div>

        <div class="grid gap-3 content-start">
        <section class="win !min-h-fit p-3 grid grid-cols-[9rem_1fr] gap-x-2 gap-y-1.5 items-center content-start">
            <label for="active">{{ __('pms.prof.active') }}</label>
            <label class="flex items-center gap-2"><input type="hidden" name="active" value="0"><input type="checkbox" id="active" name="active" value="1" @checked($active)>
                @if ($profile->status === 'blacklisted')<span class="text-danger font-semibold">{{ __('pms.prof.statuses.blacklisted') }}</span>@endif</label>
            @if ($individual)
                <label for="birth_date">{{ __('pms.prof.birthday_place') }}</label>
                <div class="grid grid-cols-[8rem_1fr] gap-2"><input id="birth_date" name="birth_date" value="{{ old('birth_date', $profile->birth_date?->format('d.m.Y')) }}" class="input @error('birth_date') input-error @enderror" autocomplete="off" placeholder="gg.aa.yyyy">
                    <input name="birth_place" value="{{ $v('birth_place') }}" maxlength="100" class="input" aria-label="{{ __('pms.prof.birth_place') }}"></div>
                <span>{{ __('pms.prof.gender') }}</span>
                <div class="flex items-center gap-4">
                    @foreach (['male', 'female'] as $g)<label class="flex items-center gap-1.5"><input type="radio" name="gender" value="{{ $g }}" @checked($v('gender') === $g)> {{ __('pms.prof.genders.'.$g) }}</label>@endforeach
                    <label class="flex items-center gap-1.5 text-muted"><input type="radio" name="gender" value="" @checked(! in_array($v('gender'), ['male', 'female'], true))> —</label>
                </div>
                <label for="nationality_code">{{ __('pms.prof.nationality') }}</label>
                <select id="nationality_code" name="nationality_code" class="input"><option value=""></option>@foreach ($countries as $c)<option value="{{ $c->code }}" @selected($v('nationality_code') === $c->code)>{{ $c->{$countryName} }}</option>@endforeach</select>
            @endif
        </section>

        {{-- Fidelio More Fields --}}
        <section class="win !min-h-fit p-3 grid grid-cols-[9rem_1fr] gap-x-2 gap-y-1.5 items-center content-start">
            <b class="col-span-2">{{ __('pms.prof.more_fields') }}</b>
            <span></span>
            <div class="flex flex-wrap gap-x-6 gap-y-1">
                @foreach (['no_mailing', 'no_emailing', 'store_private_data'] as $flag)
                    <label class="flex items-center gap-2"><input type="hidden" name="{{ $flag }}" value="0"><input type="checkbox" name="{{ $flag }}" value="1" @checked($v($flag))> {{ __('pms.prof.'.$flag) }}</label>
                @endforeach
            </div>
            <label for="default_market_segment_id">{{ __('pms.rates.market') }}</label>
            <select id="default_market_segment_id" name="default_market_segment_id" class="input"><option value=""></option>@foreach ($segments as $x)<option value="{{ $x->id }}" @selected($sel('default_market_segment_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select>
            @unless ($individual)
                <label for="default_source_code_id">{{ __('pms.rates.source') }}</label>
                <select id="default_source_code_id" name="default_source_code_id" class="input"><option value=""></option>@foreach ($sources as $x)<option value="{{ $x->id }}" @selected($sel('default_source_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select>
            @endunless
            <label for="default_rate_code_id">{{ __('pms.prof.default_rate') }}</label>
            <select id="default_rate_code_id" name="default_rate_code_id" class="input"><option value=""></option>@foreach ($rates as $x)<option value="{{ $x->id }}" @selected($sel('default_rate_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select>
            @if ($individual)
                <label for="vip_level_id">{{ __('pms.prof.vip_code') }}</label>
                <select id="vip_level_id" name="vip_level_id" class="input"><option value=""></option>@foreach ($vips as $x)<option value="{{ $x->id }}" @selected($sel('vip_level_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select>
                <label for="wedding_date">{{ __('pms.prof.wedding_date') }}</label>
                <input id="wedding_date" name="wedding_date" value="{{ old('wedding_date', $profile->wedding_date?->format('d.m.Y')) }}" class="input w-36 @error('wedding_date') input-error @enderror" autocomplete="off" placeholder="gg.aa.yyyy">
            @endif
        </section>

        @if ($individual)
            <section class="win !min-h-fit p-3">
                <div class="flex items-center justify-between"><b>{{ __('pms.prof.documents') }}</b>
                    <button type="button" class="btn !h-6" data-row-add="doc-rows">+ {{ __('pms.common.new') }}</button></div>
                <table class="grid-table mt-1 border border-line rounded" data-rows="doc-rows" data-next-index="{{ count($docs) }}">
                    <thead><tr><th>{{ __('pms.prof.doc_type') }}</th><th>{{ __('pms.prof.doc_number') }}</th><th class="w-8"></th></tr></thead>
                    <tbody>
                    @foreach ($docs as $i => $d)
                        <tr data-row>
                            <td><input type="hidden" name="documents[{{ $i }}][id]" value="{{ $d['id'] ?? '' }}">
                                <select name="documents[{{ $i }}][type]" class="input @error('documents.'.$i.'.type') input-error @enderror" aria-label="{{ __('pms.prof.doc_type') }}"><option value=""></option>
                                @foreach ($docTypes as $t)<option value="{{ $t }}" @selected(($d['type'] ?? '') === $t)>{{ __('pms.prof.doc_types.'.$t) }}</option>@endforeach</select></td>
                            <td><input name="documents[{{ $i }}][number]" value="{{ $d['number'] ?? '' }}" maxlength="40" class="input" autocomplete="off" aria-label="{{ __('pms.prof.doc_number') }}"></td>
                            <td><button type="button" class="btn !h-6 !px-2" data-row-remove title="{{ __('pms.common.delete') }}">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <template data-row-template>
                        <tr data-row>
                            <td><select name="documents[__i__][type]" class="input" aria-label="{{ __('pms.prof.doc_type') }}"><option value=""></option>
                                @foreach ($docTypes as $t)<option value="{{ $t }}">{{ __('pms.prof.doc_types.'.$t) }}</option>@endforeach</select></td>
                            <td><input name="documents[__i__][number]" maxlength="40" class="input" autocomplete="off" aria-label="{{ __('pms.prof.doc_number') }}"></td>
                            <td><button type="button" class="btn !h-6 !px-2" data-row-remove title="{{ __('pms.common.delete') }}">✕</button></td>
                        </tr>
                    </template>
                </table>
                <input type="hidden" name="documents_sent" value="1">
            </section>
        @endif
        </div>
    </div>

    <div class="flex items-center gap-4 px-3 py-2 border-t border-line text-[12px] text-muted">
        @if ($profile->exists)
            <span>{{ __('pms.prof.created_at') }}: {{ $profile->created_at?->format('d.m.Y H:i') }}</span>
        @endif
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('profiles.index', ['tur' => $profile->type, 'secili' => $profile->id]) }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
