@extends('layouts.app')
@section('title', $block->exists ? $block->code : __('pms.block.new_'.$block->type))
@section('content')
@php
    $v = fn (string $f) => old($f, $block->{$f});
    $sel = fn (string $f, $id) => (string) old($f, $block->{$f}) === (string) $id;
    $date = fn (string $f) => old($f, $block->{$f}?->format('d.m.Y'));
@endphp
<form method="POST" action="{{ $block->exists ? route('blocks.update', $block) : route('blocks.store') }}" class="win flex-1">
    @csrf
    @if ($block->exists) @method('PUT') @endif
    <div class="win-title"><span>{{ $block->exists ? $block->code.' · '.$block->name : __('pms.block.new_'.$block->type) }}</span></div>
    @if ($errors->any())<div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="flex-1 min-h-0 overflow-auto p-3 grid gap-3 content-start xl:grid-cols-[3fr_2fr]">
        <section class="win !min-h-fit p-3 grid grid-cols-2 md:grid-cols-4 gap-x-3 gap-y-2 content-start">
            <div><label class="field-label" for="code">{{ __('pms.common.code') }} *</label><input id="code" name="code" value="{{ $v('code') }}" maxlength="20" class="input uppercase @error('code') input-error @enderror" required autofocus></div>
            <div class="col-span-1 md:col-span-3"><label class="field-label" for="name">{{ __('pms.common.name') }} *</label><input id="name" name="name" value="{{ $v('name') }}" maxlength="150" class="input @error('name') input-error @enderror" required></div>
            <div><label class="field-label" for="type">{{ __('pms.common.type') }}</label>
                <select id="type" name="type" class="input">@foreach (\App\Domain\Reservations\Services\BlockService::TYPES as $t)<option value="{{ $t }}" @selected($v('type') === $t)>{{ __('pms.block.types.'.$t) }}</option>@endforeach</select></div>
            <div><label class="field-label" for="status">{{ __('pms.res.status') }}</label>
                <select id="status" name="status" class="input">@foreach (\App\Domain\Reservations\Services\BlockService::STATUSES as $s)<option value="{{ $s }}" @selected($v('status') === $s)>{{ __('pms.block.statuses.'.$s) }}</option>@endforeach</select></div>
            <div class="col-span-2"><label class="field-label" for="profile_id">{{ __('pms.block.agent') }} *</label>
                <select id="profile_id" name="profile_id" class="input @error('profile_id') input-error @enderror" required><option value=""></option>
                    @foreach ($firms as $f)<option value="{{ $f->id }}" @selected($sel('profile_id', $f->id))>{{ $f->display_name }} · {{ __('pms.prof.types.'.$f->type) }}</option>@endforeach</select></div>
            <div><label class="field-label" for="start_date">{{ __('pms.block.start') }} *</label><input id="start_date" name="start_date" value="{{ $date('start_date') }}" class="input @error('start_date') input-error @enderror" required autocomplete="off"></div>
            <div><label class="field-label" for="end_date">{{ __('pms.block.end') }} *</label><input id="end_date" name="end_date" value="{{ $date('end_date') }}" class="input @error('end_date') input-error @enderror" required autocomplete="off"></div>
            <div><label class="field-label" for="cutoff_date">{{ __('pms.block.cutoff') }}</label><input id="cutoff_date" name="cutoff_date" value="{{ $date('cutoff_date') }}" class="input" autocomplete="off"></div>
            <div><label class="field-label" for="contract_number">{{ __('pms.block.contract') }}</label><input id="contract_number" name="contract_number" value="{{ $v('contract_number') }}" maxlength="50" class="input"></div>
            <div class="col-span-2"><label class="field-label" for="rate_code_id">{{ __('pms.res.rate_code') }} *</label>
                <select id="rate_code_id" name="rate_code_id" class="input" required><option value=""></option>@foreach ($rates as $x)<option value="{{ $x->id }}" @selected($sel('rate_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="market_segment_id">{{ __('pms.card.market') }} *</label>
                <select id="market_segment_id" name="market_segment_id" class="input" required><option value=""></option>@foreach ($segments as $x)<option value="{{ $x->id }}" @selected($sel('market_segment_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
            <div><label class="field-label" for="source_code_id">{{ __('pms.card.source') }} *</label>
                <select id="source_code_id" name="source_code_id" class="input" required><option value=""></option>@foreach ($sources as $x)<option value="{{ $x->id }}" @selected($sel('source_code_id', $x->id))>{{ $x->code }} {{ $x->name }}</option>@endforeach</select></div>
            <div class="col-span-2 md:col-span-4"><label class="field-label" for="notes">{{ __('pms.block.notes') }}</label><textarea id="notes" name="notes" rows="3" class="input !h-auto py-1">{{ $v('notes') }}</textarea></div>
        </section>
        <section class="win !min-h-fit p-3 content-start">
            <b>{{ __('pms.block.rooms_per_night') }}</b>
            <p class="text-[12px] text-muted mb-2">{{ __('pms.block.rooms_hint') }}</p>
            <table class="grid-table"><thead><tr><th>{{ __('pms.res.room_type') }}</th><th class="w-24 text-right">{{ __('pms.block.rooms') }}</th></tr></thead><tbody>
                @foreach ($roomTypes as $t)
                    <tr><td><label for="rooms-{{ $t->id }}"><b>{{ $t->code }}</b> {{ $t->name }}</label></td>
                        <td><input id="rooms-{{ $t->id }}" name="rooms[{{ $t->id }}]" value="{{ old('rooms.'.$t->id, $rooms[$t->id] ?? '') }}" type="number" min="0" max="500" class="input text-right"></td></tr>
                @endforeach
            </tbody></table>
        </section>
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">{{ __('pms.common.ok') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ $block->exists ? route('blocks.show', $block) : route('blocks.index') }}" class="btn" data-hotkey="escape">{{ __('pms.common.cancel') }} <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
