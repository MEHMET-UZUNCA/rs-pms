@extends('layouts.app')
@section('title', $code->exists ? "Departman kodu {$code->code}" : 'Yeni departman kodu')
@section('content')
@php
    $action = $code->exists ? route('setup.transaction-codes.update', $code) : route('setup.transaction-codes.store');
    $v = fn (string $field) => old($field, $code->{$field});
    $codeSelect = function (string $name, string $empty) use ($allCodes, $code, $v) {
        $current = (int) $v($name);
        $html = '<select id="'.$name.'" name="'.$name.'" class="input"><option value="">'.e($empty).'</option>';
        foreach ($allCodes as $c) {
            if ($code->exists && (int) $c->id === (int) $code->id) continue;
            $html .= '<option value="'.$c->id.'"'.($current === (int) $c->id ? ' selected' : '').'>'.e($c->code.' '.$c->name).'</option>';
        }
        return $html.'</select>';
    };
    $check = fn (string $field, string $label) => '<label class="flex items-center gap-2"><input type="hidden" name="'.$field.'" value="0"><input type="checkbox" name="'.$field.'" value="1"'.(old($field, $code->{$field}) ? ' checked' : '').'> '.e($label).'</label>';
    $taxLabel = fn ($t) => $t->code.' — %'.rtrim(rtrim(number_format((float) $t->rate, 2, ',', ''), '0'), ',').($t->valid_to ? ' (bitti '.\Illuminate\Support\Carbon::parse($t->valid_to)->format('d.m.Y').')' : '');
@endphp
<form method="POST" action="{{ $action }}" class="win flex-1">
    @csrf
    @if ($code->exists) @method('PUT') @endif
    <div class="win-title">
        <span>Departman kodu — {{ $code->exists ? 'Düzenle: '.$code->code.' '.$code->name : ($copiedFrom ? 'Kopyala: '.$copiedFrom->code.' '.$copiedFrom->name : 'Yeni') }}</span>
    </div>

    @if ($errors->any())
        <div class="flash flash-error m-3 mb-0" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="flex-1 min-h-0 overflow-auto">
        <div class="grid gap-x-6 gap-y-0 p-3 lg:grid-cols-2">
            {{-- Sol sütun: Fidelio penceresinin üst yarısı --}}
            <div class="flex flex-col gap-2.5">
                <div class="grid grid-cols-[7rem_1fr_auto] gap-2 items-end">
                    <div><label class="field-label" for="code">Numara *</label>
                        <input id="code" name="code" value="{{ $v('code') }}" inputmode="numeric" maxlength="6" class="input @error('code') input-error @enderror" required autofocus></div>
                    <div><label class="field-label" for="category">Kısa kod</label>
                        <input id="category" name="category" value="{{ $v('category') }}" maxlength="5" class="input uppercase w-24"></div>
                    <label class="flex items-center gap-2 pb-1"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $code->is_active ?? true))> Aktif</label>
                </div>
                <div><label class="field-label" for="name">Açıklama *</label>
                    <input id="name" name="name" value="{{ $v('name') }}" maxlength="100" class="input @error('name') input-error @enderror" required></div>
                <div><label class="field-label" for="folio_text">Folyo metni <span class="text-muted">(boşsa açıklama basılır)</span></label>
                    <input id="folio_text" name="folio_text" value="{{ $v('folio_text') }}" maxlength="100" class="input"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="type">Tip *</label>
                        <select id="type" name="type" class="input" data-code-type>
                            @foreach (\App\Http\Controllers\Setup\TransactionCodeController::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected($v('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="field-label" for="group_id">Grup *</label>
                        <select id="group_id" name="group_id" class="input" required>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected((int) $v('group_id') === (int) $group->id)>{{ $group->code }} {{ $group->name }}</option>
                            @endforeach
                        </select></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="arrangement_code">Arrangement</label>
                        <input id="arrangement_code" name="arrangement_code" value="{{ $v('arrangement_code') }}" maxlength="10" class="input uppercase" placeholder="ör. ACC"></div>
                    <div><label class="field-label" for="government_code">Resmi kod (Government Code)</label>
                        <input id="government_code" name="government_code" value="{{ $v('government_code') }}" maxlength="20" class="input"></div>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1">{!! $check('manual_post_allowed', 'Elle kayıt yapılabilir (Allow Manual Post)') !!}</div>
                <div><label class="field-label" for="return_transaction_code_id">İade kodu (Return Dep.)</label>{!! $codeSelect('return_transaction_code_id', '<Aynı kod>') !!}</div>
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="field-label" for="unit_description">Birim</label>
                        <input id="unit_description" name="unit_description" value="{{ $v('unit_description') }}" maxlength="50" class="input"></div>
                    <div><label class="field-label" for="default_price">Varsayılan fiyat</label>
                        <input id="default_price" name="default_price" value="{{ $v('default_price') }}" inputmode="decimal" class="input"></div>
                    <div><label class="field-label" for="physical_origin">Physical Origin</label>
                        <input id="physical_origin" name="physical_origin" value="{{ $v('physical_origin') }}" maxlength="30" class="input"></div>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <span class="field-label !mb-0">Vergi (tarihli) — boş bitiş: süresiz</span>
                        <button type="button" class="btn !h-6" data-row-add="tax-rows">+ Yeni</button>
                    </div>
                    <table class="grid-table mt-1 border border-line rounded" data-rows="tax-rows" data-next-index="{{ count($taxRows) }}">
                        <thead><tr><th>Vergi</th><th>Başlangıç</th><th>Bitiş</th><th class="w-8"></th></tr></thead>
                        <tbody>
                        @foreach ($taxRows as $i => $row)
                            <tr data-row>
                                <td><select name="taxes[{{ $i }}][tax_id]" class="input" aria-label="Vergi">
                                    <option value="">—</option>
                                    @foreach ($taxes as $tax)<option value="{{ $tax->id }}" @selected((int) ($row['tax_id'] ?? 0) === (int) $tax->id)>{{ $taxLabel($tax) }}</option>@endforeach
                                </select></td>
                                <td><input type="date" name="taxes[{{ $i }}][valid_from]" value="{{ $row['valid_from'] ?? '' }}" class="input" aria-label="Başlangıç"></td>
                                <td><input type="date" name="taxes[{{ $i }}][valid_to]" value="{{ $row['valid_to'] ?? '' }}" class="input" aria-label="Bitiş"></td>
                                <td><button type="button" class="btn !h-6 !px-2" data-row-remove title="Satırı sil">✕</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <template data-row-template>
                            <tr data-row>
                                <td><select name="taxes[__i__][tax_id]" class="input" aria-label="Vergi"><option value="">—</option>
                                    @foreach ($taxes->whereNull('valid_to') as $tax)<option value="{{ $tax->id }}">{{ $taxLabel($tax) }}</option>@endforeach
                                </select></td>
                                <td><input type="date" name="taxes[__i__][valid_from]" class="input" aria-label="Başlangıç"></td>
                                <td><input type="date" name="taxes[__i__][valid_to]" class="input" aria-label="Bitiş"></td>
                                <td><button type="button" class="btn !h-6 !px-2" data-row-remove title="Satırı sil">✕</button></td>
                            </tr>
                        </template>
                    </table>
                    <label class="flex items-center gap-2 mt-1.5"><input type="hidden" name="is_tax_inclusive" value="0"><input type="checkbox" name="is_tax_inclusive" value="1" @checked(old('is_tax_inclusive', $code->is_tax_inclusive ?? true))> Tutar vergi dahil girilir</label>
                </div>

                <fieldset class="border border-line rounded px-3 pb-2">
                    <legend class="px-1 text-[12px] text-muted">Kapalı (Disabled)</legend>
                    <div class="flex flex-wrap gap-x-5 gap-y-1">
                        {!! $check('disabled_in_prearrival', 'Ön kayıtta') !!}{!! $check('disabled_in_billing', 'Folyoda') !!}
                        {!! $check('disabled_in_ar', 'Cari hesapta') !!}{!! $check('disabled_in_payments', 'Ödemelerde') !!}
                    </div>
                </fieldset>
            </div>

            {{-- Sağ sütun: Fidelio penceresinin alt yarısı --}}
            <div class="flex flex-col gap-2.5 max-lg:mt-2.5">
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="payment_kind">Ödeme türü (Payment Type)</label>
                        <select id="payment_kind" name="payment_kind" class="input">
                            <option value="">&lt;Yok&gt;</option>
                            @foreach (\App\Http\Controllers\Setup\TransactionCodeController::PAYMENT_KINDS as $value => $label)
                                <option value="{{ $value }}" @selected($v('payment_kind') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="field-label" for="currency_code">Döviz (Foreign Currency)</label>
                        <select id="currency_code" name="currency_code" class="input">
                            <option value="">&lt;Yok&gt;</option>
                            @foreach ($currencies as $currency)<option value="{{ $currency->code }}" @selected($v('currency_code') === $currency->code)>{{ $currency->code }} {{ $currency->name }}</option>@endforeach
                        </select></div>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1">{!! $check('direct_debit', 'Direct Debit Payment') !!}{!! $check('non_hotel_revenue', 'Otel dışı gelir (Non-Hotel Revenue)') !!}</div>
                <div class="grid grid-cols-[1fr_6rem] gap-2">
                    <div><label class="field-label" for="cc_commission_transaction_code_id">Kart komisyonu kodu (CC Commission)</label>{!! $codeSelect('cc_commission_transaction_code_id', '<Yok>') !!}</div>
                    <div><label class="field-label" for="cc_commission_pct">Yüzde</label>
                        <input id="cc_commission_pct" name="cc_commission_pct" value="{{ $v('cc_commission_pct') }}" inputmode="decimal" class="input"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="revenue_bucket">İstatistik tipi (Statistic type)</label>
                        <select id="revenue_bucket" name="revenue_bucket" class="input">
                            <option value="">&lt;Yok&gt;</option>
                            @foreach (\App\Http\Controllers\Setup\TransactionCodeController::BUCKETS as $value => $label)
                                <option value="{{ $value }}" @selected($v('revenue_bucket') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="field-label" for="food_beverage">Yiyecek / içecek</label>
                        <select id="food_beverage" name="food_beverage" class="input">
                            <option value="">—</option>
                            @foreach (['food' => 'Yiyecek', 'beverage' => 'İçecek', 'other' => 'Diğer'] as $value => $label)
                                <option value="{{ $value }}" @selected($v('food_beverage') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="field-label" for="credit_limit">Kredi limiti</label>
                        <input id="credit_limit" name="credit_limit" value="{{ $v('credit_limit') }}" inputmode="decimal" class="input"></div>
                    <div><label class="field-label" for="folio_subfunction">Folio Subfunction</label>
                        <input id="folio_subfunction" name="folio_subfunction" value="{{ $v('folio_subfunction') }}" maxlength="30" class="input"></div>
                </div>
                <div><label class="field-label" for="adjustment_transaction_code_id">Düzeltme kodu (Adjustment Dept)</label>{!! $codeSelect('adjustment_transaction_code_id', '<Yok>') !!}</div>
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    {!! $check('open_cash_drawer', 'Kasa çekmecesini aç') !!}{!! $check('hostess_enabled', 'Hostess Enabled') !!}{!! $check('post_tax_only', 'Yalnız vergi kaydı (Post Tax Only)') !!}
                </div>

                <fieldset class="border border-line rounded px-3 pb-2 flex flex-col gap-2">
                    <legend class="px-1 text-[12px] text-muted">RS PMS</legend>
                    <div class="flex flex-wrap gap-x-5 gap-y-1">{!! $check('is_discount', 'İndirim / rebate kodu') !!}{!! $check('is_no_show', 'No-show kodu') !!}</div>
                    <div><label class="field-label" for="discount_of_transaction_code_id">İndirimin ait olduğu asıl kod</label>{!! $codeSelect('discount_of_transaction_code_id', '<Yok>') !!}</div>
                    <div><label class="field-label" for="outlet_id">Outlet</label>
                        <select id="outlet_id" name="outlet_id" class="input">
                            <option value="">&lt;Yok&gt;</option>
                            @foreach ($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((int) $v('outlet_id') === (int) $outlet->id)>{{ $outlet->code }} {{ $outlet->name }}</option>@endforeach
                        </select></div>
                </fieldset>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 px-3 py-2 border-t border-line">
        <span class="text-[12px] text-muted">Vergi değişikliği geçmişi bozmaz: eski satırı bitiş tarihiyle kapatın, yeni satırı başlangıç tarihiyle ekleyin.</span>
        <button class="btn btn-primary ml-auto" data-hotkey="f10 ctrl+enter">Tamam <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
        <a href="{{ route('setup.transaction-codes.index', ['secili' => $code->id]) }}" class="btn" data-hotkey="escape">İptal <span class="kbd">Esc</span></a>
    </div>
</form>
@endsection
