@extends('layouts.app')
@section('title', __('pms.prof.title'))
@section('content')
@php $types = \App\Domain\Profiles\Services\ProfileService::TYPES; $canEdit = Gate::allows('permission', 'profiles.edit'); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.prof.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.prof.subtitle') }}</span></div>
    <div class="flex flex-1 min-h-0">
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="flex items-end gap-0.5 px-3 pt-2 border-b border-line" role="tablist">
                @foreach ($types as $t)
                    <a href="{{ route('profiles.index', ['tur' => $t, 'ad' => $name ?: null, 'pasif' => $showInactive ?: null]) }}" role="tab" aria-selected="{{ $type === $t ? 'true' : 'false' }}" data-hotkey="alt+{{ $loop->iteration }}"
                       @class(['px-3 h-8 -mb-px inline-flex items-center gap-2 border rounded-t text-[13px]', 'bg-surface border-line border-b-surface font-semibold' => $type === $t, 'border-transparent text-muted' => $type !== $t])>
                        {{ __('pms.prof.types.'.$t) }} <span class="text-[11px] px-1.5 rounded bg-panel">{{ $counts[$t] ?? 0 }}</span> <span class="kbd">Alt+{{ $loop->iteration }}</span></a>
                @endforeach
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3 px-3 py-2 border-b border-line">
                <input type="hidden" name="tur" value="{{ $type }}">
                <label class="flex flex-col"><span class="field-label">{{ $type === 'individual' ? __('pms.prof.name') : __('pms.prof.company_name') }}</span>
                    <input name="ad" value="{{ $name }}" class="input w-56" autofocus autocomplete="off" placeholder="{{ __('pms.prof.name_hint') }}"></label>
                <label class="flex flex-col"><span class="field-label">{{ __('pms.prof.city') }}</span><input name="sehir" value="{{ $city }}" class="input w-36" autocomplete="off"></label>
                <label class="flex flex-col"><span class="field-label">{{ __('pms.prof.phone_email') }}</span><input name="iletisim" value="{{ $contact }}" class="input w-48" autocomplete="off"></label>
                <label class="flex items-center gap-1.5 pb-2"><input type="checkbox" name="pasif" value="1" @checked($showInactive)> {{ __('pms.common.show_inactive') }}</label>
                <button class="btn btn-primary" data-hotkey="f3">{{ __('pms.common.search') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F3</span></button>
                <span class="ml-auto text-[12px] text-muted pb-2">{{ __('pms.common.records', ['count' => $rows->count()]) }}@if ($rows->count() >= $limit) · {{ __('pms.res.limit_hint', ['limit' => $limit]) }}@endif</span>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                <table class="grid-table" id="profile-grid" data-grid data-selected="{{ $selected }}" data-detail-target="#profile-summary">
                    <thead><tr><th>{{ __('pms.prof.name') }}</th>@if ($type === 'individual')<th>{{ __('pms.prof.nationality') }}</th><th>{{ __('pms.prof.language') }}</th>@else<th>{{ __('pms.prof.tax_number') }}</th><th>IATA</th>@endif
                        <th>{{ __('pms.prof.city') }}</th><th>{{ __('pms.prof.phone') }}</th><th>{{ __('pms.users.email') }}</th><th class="text-right">{{ __('pms.prof.stays') }}</th><th>{{ __('pms.prof.last_stay') }}</th><th>{{ __('pms.res.status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $pr)
                        @php $addr = $pr->addresses->first(); $c = fn ($t) => $pr->communications->where('type', $t)->sortByDesc('is_primary')->first()?->value; $st = $stays[$pr->id] ?? null; @endphp
                        <tr data-id="{{ $pr->id }}" data-url-detail="{{ route('profiles.summary', $pr) }}" @if ($canEdit) data-url-edit="{{ route('profiles.edit', $pr) }}" data-url-merge="{{ route('profiles.merge-form', $pr) }}" @endif @class(['is-inactive' => $pr->status === 'inactive'])>
                            <td class="font-semibold">{{ $pr->display_name }}@if ($pr->title) <span class="font-normal text-muted">{{ $pr->title }}</span>@endif</td>
                            @if ($type === 'individual')<td>{{ $pr->nationality_code }}</td><td>{{ strtoupper((string) $pr->language_code) }}</td>@else<td>{{ $pr->tax_number }}</td><td>{{ $pr->iata_code }}</td>@endif
                            <td>{{ $addr?->city }}</td><td>{{ $c('mobile') ?? $c('phone') }}</td><td>{{ $c('email') }}</td>
                            <td class="text-right">{{ $st?->stays }}</td><td>{{ $st ? \Illuminate\Support\Carbon::parse($st->last_stay)->format('d.m.Y') : '' }}</td>
                            <td>@if ($pr->status === 'blacklisted')<span class="text-danger font-semibold">{{ __('pms.prof.statuses.blacklisted') }}</span>@elseif ($pr->status === 'inactive'){{ __('pms.prof.statuses.inactive') }}@endif</td>
                        </tr>
                    @endforeach
                    <tr data-grid-empty @if ($rows->isNotEmpty()) hidden @endif><td colspan="9" class="text-center text-muted py-6">{{ __('pms.common.no_records') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="profile-summary" class="border-t border-line h-64 overflow-auto bg-surface" data-empty='<p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p>'><p class="p-3 text-muted">{{ __('pms.common.select_row') }}</p></div>
        </section>
        <aside class="w-40 shrink-0 border-l border-line p-2 flex flex-col gap-1.5">
            @if ($canEdit)
                <button type="button" class="btn btn-side" data-modal-open="profile-type-dialog" data-hotkey="alt+n insert">{{ __('pms.common.new') }} <span class="kbd ml-auto">Alt+N</span></button>
                <a class="btn btn-side" data-needs-selection="profile-grid" data-action="edit" data-hotkey="alt+e">{{ __('pms.common.edit') }} <span class="kbd ml-auto">Enter</span></a>
                <a class="btn btn-side" data-needs-selection="profile-grid" data-action="merge" data-hotkey="alt+m">{{ __('pms.merge.button') }} <span class="kbd ml-auto">Alt+M</span></a>
            @endif
            <a href="{{ route('home') }}" class="btn btn-side mt-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd ml-auto">Esc</span></a>
        </aside>
    </div>
</div>
@if ($canEdit)
{{-- Fidelio "Select Profile Type": yeni profilden önce tip seçilir (↑/↓, Enter ya da çift tık) --}}
<div id="profile-type-dialog" data-modal hidden class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center p-4">
    <form method="GET" action="{{ route('profiles.choose') }}" class="win w-80 max-w-full" role="dialog" aria-modal="true" aria-labelledby="profile-type-title">
        <div class="win-title"><span id="profile-type-title">{{ __('pms.prof.select_type') }}</span></div>
        <div class="p-3 flex gap-3">
            <select name="tur" size="{{ count($types) }}" class="input !h-auto flex-1" data-submit-on-enter data-autofocus aria-label="{{ __('pms.prof.type') }}">
                @foreach ($types as $t)<option value="{{ $t }}" @selected($type === $t)>{{ __('pms.prof.types.'.$t) }}</option>@endforeach
            </select>
            <div class="flex flex-col gap-1.5 w-24">
                <button class="btn btn-primary">{{ __('pms.common.ok') }}</button>
                <button type="button" class="btn mt-auto" data-modal-close data-hotkey="escape">{{ __('pms.common.cancel') }}</button>
            </div>
        </div>
    </form>
</div>
@endif
@endsection
