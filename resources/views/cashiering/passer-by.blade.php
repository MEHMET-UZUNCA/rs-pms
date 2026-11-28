@extends('layouts.app')
@section('title', __('pms.passer.title'))
@section('content')
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.passer.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.passer.subtitle') }}</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 flex flex-col gap-4">
        <form method="POST" action="{{ route('cashiering.passer-by.store') }}" class="win !min-h-fit p-3 flex flex-wrap items-end gap-3 max-w-3xl" data-passer-by>
            @csrf
            <label class="flex flex-col flex-1 min-w-56"><span class="field-label">{{ __('pms.passer.name') }}</span>
                <input name="name" value="{{ old('name') }}" maxlength="100" class="input" placeholder="{{ __('pms.passer.default_name') }}" autofocus></label>
            <label class="flex flex-col flex-1 min-w-56"><span class="field-label">{{ __('pms.passer.note') }}</span>
                <input name="notes" value="{{ old('notes') }}" maxlength="500" class="input"></label>
            <button class="btn btn-primary" data-hotkey="f10">{{ __('pms.passer.open') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        </form>
        <section class="max-w-3xl">
            <b>{{ __('pms.passer.today') }}</b>
            <table class="grid-table mt-1">
                <thead><tr><th class="w-24">{{ __('pms.reports.col.folio') }}</th><th>{{ __('pms.passer.name') }}</th><th class="w-24">{{ __('pms.res.status') }}</th><th class="w-32 text-right">{{ __('pms.reports.col.balance') }}</th></tr></thead>
                <tbody>
                @forelse ($accounts as $a)
                    <tr @class(['is-inactive' => $a->status !== 'open'])>
                        <td><a class="text-brand-600 hover:underline font-semibold" href="{{ route('billing.account', $a) }}">{{ $a->folio_number }}</a></td><td>{{ $a->name }}</td>
                        <td>{{ __('pms.passer.statuses.'.$a->status) }}</td><td class="text-right tabular-nums">{{ number_format((float) $a->balance, 2, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('pms.common.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="text-[12.5px] text-muted mt-2">{{ __('pms.passer.hint') }}</p>
        </section>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
@endsection
