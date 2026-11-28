@extends('layouts.app')
@section('title', __('pms.qpost.title'))
@section('content')
@php $lineErrors = session('line_errors', []); $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<div class="win flex-1">
    <div class="win-title"><span>{{ __('pms.qpost.title') }}</span><span class="font-normal text-brand-100 text-[12px]">{{ __('pms.qpost.subtitle') }}</span></div>
    <div class="flex-1 min-h-0 overflow-auto p-3 flex flex-col gap-4">
        <form method="POST" action="{{ route('cashiering.posting.store') }}" class="max-w-5xl" data-quick-posting>
            @csrf
            <datalist id="qp-codes">@foreach ($codes as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach</datalist>
            <table class="grid-table">
                <thead><tr><th class="w-8">#</th><th class="w-28">{{ __('pms.qpost.target') }}</th><th class="w-28">{{ __('pms.billing.department_code') }}</th><th class="w-44"></th>
                    <th class="w-20 text-right">{{ __('pms.billing.qty') }}</th><th class="w-32 text-right">{{ __('pms.billing.unit_price') }}</th><th>{{ __('pms.qpost.description') }}</th><th class="w-28">{{ __('pms.qpost.reference') }}</th></tr></thead>
                <tbody>
                @for ($i = 0; $i < $rows; $i++)
                    @php $n = $i + 1; $err = $lineErrors[$n] ?? null; @endphp
                    <tr @class(['bg-danger/5' => $err]) data-qp-row>
                        <td class="text-muted">{{ $n }}</td>
                        <td><input name="lines[{{ $i }}][target]" value="{{ old("lines.$i.target") }}" class="input" autocomplete="off" @if ($i === 0) autofocus @endif aria-label="{{ __('pms.qpost.target') }} {{ $n }}"></td>
                        <td><input name="lines[{{ $i }}][code]" value="{{ old("lines.$i.code") }}" list="qp-codes" class="input" autocomplete="off" data-qp-code aria-label="{{ __('pms.billing.department_code') }} {{ $n }}"></td>
                        <td class="text-[12px] text-muted truncate max-w-44" data-qp-name>@if ($err)<span class="text-danger">{{ $err }}</span>@endif</td>
                        <td><input name="lines[{{ $i }}][qty]" value="{{ old("lines.$i.qty") }}" inputmode="decimal" class="input text-right" placeholder="1" aria-label="{{ __('pms.billing.qty') }} {{ $n }}"></td>
                        <td><input name="lines[{{ $i }}][amount]" value="{{ old("lines.$i.amount") }}" inputmode="decimal" class="input text-right" data-qp-amount aria-label="{{ __('pms.billing.unit_price') }} {{ $n }}"></td>
                        <td><input name="lines[{{ $i }}][description]" value="{{ old("lines.$i.description") }}" maxlength="100" class="input" aria-label="{{ __('pms.qpost.description') }} {{ $n }}"></td>
                        <td><input name="lines[{{ $i }}][reference]" value="{{ old("lines.$i.reference") }}" maxlength="100" class="input" aria-label="{{ __('pms.qpost.reference') }} {{ $n }}"></td>
                    </tr>
                @endfor
                </tbody>
            </table>
            <div class="flex items-center gap-2 mt-2">
                <span class="text-[12.5px] text-muted">{{ __('pms.qpost.hint') }}</span>
                <button class="btn btn-primary ml-auto" data-hotkey="f10">{{ __('pms.qpost.post') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
            </div>
        </form>

        <section class="max-w-5xl">
            <b>{{ __('pms.qpost.recent') }}</b>
            <table class="grid-table mt-1">
                <thead><tr><th class="w-16">{{ __('pms.reports.col.time') }}</th><th class="w-28">{{ __('pms.qpost.target') }}</th><th class="w-20">{{ __('pms.common.code') }}</th><th>{{ __('pms.qpost.description') }}</th>
                    <th class="w-16 text-right">{{ __('pms.billing.qty') }}</th><th class="w-28 text-right">{{ __('pms.billing.amount') }}</th><th class="w-28">{{ __('pms.qpost.reference') }}</th></tr></thead>
                <tbody>
                @forelse ($recent as $r)
                    <tr><td class="tabular-nums">{{ substr((string) $r->created_at, 11, 5) }}</td><td>{{ $r->room ?? ($r->folio_number.' '.$r->account) }}</td><td>{{ $r->code }}</td><td>{{ $r->description }}</td>
                        <td class="text-right">{{ (float) $r->quantity }}</td><td class="text-right tabular-nums">{{ $money($r->amount) }}</td><td>{{ $r->reference }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('pms.common.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
    <div class="flex px-3 py-2 border-t border-line"><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
</div>
<script>
    // Departman kodu yazılınca adı ve varsayılan fiyatı gösterilir; Enter bir sonraki alana geçer (son alanda formu göndermez)
    (() => {
        const codes = @json($codes->mapWithKeys(fn ($c) => [$c->code => ['name' => $c->name, 'price' => $c->default_price]]));
        document.querySelectorAll('[data-qp-row]').forEach((row) => {
            const code = row.querySelector('[data-qp-code]'), name = row.querySelector('[data-qp-name]'), amount = row.querySelector('[data-qp-amount]');
            code.addEventListener('change', () => {
                const c = codes[code.value.trim()];
                name.textContent = c ? c.name : (code.value ? '?' : '');
                if (c && c.price && !amount.value) amount.value = String(c.price).replace('.', ',');
            });
        });
        const inputs = [...document.querySelectorAll('[data-quick-posting] input:not([type=hidden])')];
        inputs.forEach((el, i) => el.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); inputs[i + 1]?.focus(); } }));
    })();
</script>
@endsection
