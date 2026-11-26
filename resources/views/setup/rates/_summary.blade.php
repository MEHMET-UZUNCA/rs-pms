@php
    $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '.');
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : '';
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
@endphp
<div class="p-4 text-[13px]" data-rate-summary="{{ $rate->code }}">
    <h2 class="text-center text-[20px] font-semibold text-muted">{{ $rate->code }} / {{ $rate->name }}</h2>
    <h3 class="mt-3 text-[16px] font-semibold text-brand-700">{{ __('pms.rates.header_info') }}</h3>
    <table class="grid-table mt-1"><tbody>
        @foreach ([
            'startsell' => $date($rate->valid_from), 'endsell' => $date($rate->valid_to), 'min_los' => $rate->min_los, 'max_los' => $rate->max_los,
            'folio_text' => $rate->folio_text, 'market' => $rate->default_market_segment_id ? DB::table('pms_market_segments')->where('id', $rate->default_market_segment_id)->value('name') : '',
            'department_code' => $rate->roomTransactionCode?->name, 'weekend_department_code' => $rate->weekend_transaction_code_id ? DB::table('pms_transaction_codes')->where('id', $rate->weekend_transaction_code_id)->value('name') : '',
            'board' => $rate->package ? $rate->package->code.' - '.$rate->package->name : __('pms.rates.no_board'), 'currency' => $rate->currency_code,
        ] as $label => $value)
            <tr><td class="font-semibold w-64">{{ __('pms.rates.'.$label) }}</td><td class="text-right">{{ $value }}</td></tr>
        @endforeach
    </tbody></table>
    @if ($rate->package && $rate->package->elements->isNotEmpty())
        <div class="mt-2 font-semibold">{{ __('pms.rates.package_link') }}</div>
        @foreach ($rate->package->elements as $e)<div class="text-center">{{ $e->transactionCode?->name }} {{ $money($e->amount_adult) }}</div>@endforeach
    @endif

    <h3 class="mt-4 text-[16px] font-semibold text-brand-700">{{ __('pms.rates.detail_info') }}</h3>
    @forelse ($details as $d)
        <div class="mt-2 rounded border border-line" data-rate-detail>
            <div class="flex flex-wrap gap-x-6 px-2 py-1 bg-panel">
                <span><b>{{ __('pms.rates.period') }}</b> {{ $date($d->date_from) }} – {{ $date($d->date_to) }}</span>
                @if ($d->season)<span>{{ $d->season->name }}</span>@endif
                <span>{{ $d->roomTypes->pluck('code')->implode(', ') ?: __('pms.rates.all_room_types') }}</span>
                @if ($d->room_numbers)<span>{{ __('pms.rates.rooms') }} {{ $d->room_numbers }}</span>@endif
                @if ($d->weekdays !== 127)<span>{{ collect($days)->filter(fn ($k, $i) => $d->weekdays & (1 << $i))->map(fn ($k) => __('pms.rates.days.'.$k))->implode(' ') }}</span>@endif
            </div>
            <table class="grid-table"><thead><tr><th>{{ __('pms.rates.adults') }}</th><th class="text-right">{{ __('pms.rates.price') }}</th><th class="text-right">{{ __('pms.rates.weekend_price') }}</th></tr></thead><tbody>
                @foreach (['default', 1, 2, 3, 4] as $n)
                    @if ($d->{'price_'.$n} !== null || $d->{'weekend_price_'.$n} !== null)
                        <tr><td>{{ $n === 'default' ? 'Default' : $n }}</td><td class="text-right tabular-nums">{{ $money($d->{'price_'.$n}) }}</td><td class="text-right tabular-nums">{{ $money($d->{'weekend_price_'.$n}) }}</td></tr>
                    @endif
                @endforeach
                @foreach ([1, 2, 3] as $b)
                    @if ($d->{'child_band_'.$b} !== null)
                        <tr><td>CA{{ $b }}</td><td class="text-right tabular-nums">{{ $money($d->{'child_band_'.$b}) }}</td><td class="text-right tabular-nums">{{ $money($d->{'weekend_child_band_'.$b}) }}</td></tr>
                    @endif
                @endforeach
            </tbody></table>
        </div>
    @empty
        <p class="text-muted mt-1">{{ __('pms.rates.no_details') }}</p>
    @endforelse
</div>
