@extends('layouts.app')
@section('title', __('pms.merge.title'))
@section('content')
@php
    $d = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d.m.Y') : '';
    $rows = $candidates->keyBy('id');
    foreach ($found as $f) { $rows[$f->id] ??= (object) ['id' => $f->id, 'display_name' => $f->display_name, 'birth_date' => $f->birth_date, 'nationality_code' => $f->nationality_code, 'reasons' => ['search'], 'reservations' => null]; }
@endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.merge.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ $profile->display_name }} · {{ __('pms.prof.types.'.$profile->type) }}</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 flex flex-col gap-3">
        <section class="win !min-h-fit p-3 text-[13px]">
            <b>{{ $profile->display_name }}</b> <span class="text-muted">#{{ $profile->id }} · {{ $d($profile->birth_date) }} · {{ $profile->nationality_code }} · {{ $profile->communications->pluck('value')->implode(' · ') }}</span>
            <p class="text-[12px] text-muted mt-1">{{ __('pms.merge.hint') }}</p>
        </section>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <label class="flex flex-col"><span class="field-label">{{ __('pms.merge.search') }}</span><input name="ara" value="{{ $q }}" class="input w-64" autocomplete="off" autofocus></label>
            <button class="btn" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd">F3</span></button>
        </form>
        <section class="win !min-h-fit p-3">
            <b>{{ __('pms.merge.candidates') }}</b>
            <div class="overflow-x-auto"><table class="grid-table mt-1 min-w-[40rem]" data-merge-candidates>
                <thead><tr><th>{{ __('pms.prof.name') }}</th><th>{{ __('pms.prof.birth_date') }}</th><th>{{ __('pms.prof.nationality') }}</th><th>{{ __('pms.merge.match') }}</th>
                    <th class="text-right">{{ __('pms.prof.stays') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $c)
                    <tr><td class="font-semibold">{{ $c->display_name }} <span class="text-muted font-normal text-[11px]">#{{ $c->id }}</span></td><td>{{ $d($c->birth_date) }}</td><td>{{ $c->nationality_code }}</td>
                        <td class="text-[12px]">{{ collect($c->reasons)->map(fn ($r) => __('pms.merge.reasons.'.$r))->implode(', ') }}</td><td class="text-right">{{ $c->reservations }}</td>
                        <td class="whitespace-nowrap">
                            <form method="POST" action="{{ route('profiles.merge', $profile) }}" class="inline" data-confirm="{{ __('pms.merge.confirm', ['drop' => $c->display_name, 'keep' => $profile->display_name]) }}">@csrf
                                <input type="hidden" name="other_id" value="{{ $c->id }}"><input type="hidden" name="keep" value="this">
                                <button class="btn !h-7">{{ __('pms.merge.into_this') }}</button></form>
                            <form method="POST" action="{{ route('profiles.merge', $profile) }}" class="inline" data-confirm="{{ __('pms.merge.confirm', ['drop' => $profile->display_name, 'keep' => $c->display_name]) }}">@csrf
                                <input type="hidden" name="other_id" value="{{ $c->id }}"><input type="hidden" name="keep" value="other">
                                <button class="btn !h-7">{{ __('pms.merge.into_other') }}</button></form>
                        </td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('pms.merge.none') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('profiles.index', ['tur' => $profile->type, 'secili' => $profile->id]) }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
