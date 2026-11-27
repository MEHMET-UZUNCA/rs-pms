<?php

namespace App\Domain\Cashiering\Services;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\NumberSequenceService;
use Illuminate\Support\Facades\DB;

/**
 * Fatura (Fidelio Print Invoice): folyonun henüz faturalanmamış harcamaları faturaya girer (ters çevrilenler ve ters kayıtlar hariç),
 * kayıtlar faturaya bağlanır (tekrar faturalanmaz). Numara "invoice" serisinden boşluksuz alınır. Alıcı: folyonun ödeyeni ya da misafir,
 * vergi dairesi / no ve adres profilden; ekranda değiştirilebilir.
 * Otel kuralı: anlık (bireysel) müşterilerin faturası PMS'ten, cari hesapların (şirket / acenta / kaynak ödeyen ya da City Ledger ile
 * kapanan folyolar) faturası Logo'dan kesilir; böyle bir folyoda PMS faturası ancak bilerek ("yine de PMS'te kes") kesilir.
 */
class InvoiceService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    /** @return array{name: string, tax_office: ?string, tax_number: ?string, address: ?string, profile_id: ?int} */
    public function defaultBillTo(Folio $folio): array
    {
        $profileId = $folio->payee_profile_id ?? $folio->reservation?->profile_id;
        $profile = $profileId ? DB::table('pms_profiles')->where('id', $profileId)->first() : null;
        $address = $profileId ? DB::table('pms_profile_addresses')->where('profile_id', $profileId)->orderByDesc('is_primary')->first() : null;

        return [
            'profile_id' => $profile?->id, 'name' => $profile?->display_name ?? (string) $folio->name,
            'tax_office' => $profile?->tax_office, 'tax_number' => $profile?->tax_number,
            'address' => $address ? trim(implode(', ', array_filter([$address->line1, $address->line2, trim(($address->postal_code ?? '').' '.($address->city ?? '')), $address->country_code]))) : null,
        ];
    }

    /**
     * Folyo cari hesaba mı gidiyor? (Faturası Logo'dan kesilir.) Ödeyen şirket / acenta / kaynak profili ya da folyoda City Ledger ödemesi.
     *
     * @return string|null neden (null: anlık müşteri, PMS'te kesilir)
     */
    public function accountReason(Folio $folio): ?string
    {
        $payee = $folio->payee_profile_id ? DB::table('pms_profiles')->where('id', $folio->payee_profile_id)->first(['type', 'display_name']) : null;
        if ($payee && in_array($payee->type, ['company', 'travel_agent', 'source'], true)) {
            return __('pms.invoice.reason_payee', ['name' => $payee->display_name]);
        }
        $cityLedger = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $folio->id)->whereNull('p.reversed_by_id')->whereNull('p.reversal_of_id')->where('t.payment_kind', 'city_ledger')->value('t.code');

        return $cityLedger ? __('pms.invoice.reason_city_ledger', ['code' => $cityLedger]) : null;
    }

    /** Faturalanacak kayıtlar. */
    public function pending(Folio $folio)
    {
        return DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $folio->id)->whereNull('p.invoice_id')->whereNull('p.reversed_by_id')->whereNull('p.reversal_of_id')
            ->whereNotIn('t.type', ['payment', 'deposit', 'paid_out'])->orderBy('p.id')
            ->get(['p.id', 'p.transaction_code_id', 'p.description', 'p.stay_date', 'p.business_date', 'p.quantity', 'p.unit_price', 'p.amount', 'p.net_amount', 'p.tax_amount']);
    }

    /** @param array{name: string, tax_office?: ?string, tax_number?: ?string, address?: ?string, profile_id?: ?int} $billTo */
    public function issue(Folio $folio, array $billTo, ?int $userId, bool $force = false): int
    {
        if (! $force && ($reason = $this->accountReason($folio))) {
            throw new PostingException(__('pms.invoice.logo_only', ['reason' => $reason]));
        }

        return DB::transaction(function () use ($folio, $billTo, $userId) {
            $lines = $this->pending($folio);
            if ($lines->isEmpty()) {
                throw new PostingException(__('pms.invoice.nothing'));
            }
            $property = Property::query()->findOrFail($folio->property_id);
            $paid = -(float) DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
                ->where('p.folio_id', $folio->id)->whereIn('t.type', ['payment', 'deposit'])->sum('p.amount');
            $id = DB::table('pms_invoices')->insertGetId([
                'property_id' => $property->id, 'invoice_number' => $this->numbers->next($property->id, 'invoice'), 'type' => 'invoice', 'status' => 'issued',
                'folio_id' => $folio->id, 'reservation_id' => $folio->reservation_id, 'bill_to_profile_id' => $billTo['profile_id'] ?? null,
                'bill_to_name' => mb_substr($billTo['name'], 0, 250), 'bill_to_tax_office' => $billTo['tax_office'] ?? null, 'bill_to_tax_number' => $billTo['tax_number'] ?? null,
                'bill_to_address' => isset($billTo['address']) ? mb_substr((string) $billTo['address'], 0, 500) : null,
                'business_date' => $property->current_business_date->toDateString(), 'issued_at' => now(), 'issued_by' => $userId, 'currency_code' => $property->base_currency,
                'total_net' => round($lines->sum('net_amount'), 2), 'total_tax' => round($lines->sum('tax_amount'), 2), 'total_gross' => round($lines->sum('amount'), 2),
                'total_paid' => round(max(0, $paid), 2), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $taxes = DB::table('pms_posting_taxes as pt')->join('pms_taxes as x', 'x.id', '=', 'pt.tax_id')->whereIn('pt.posting_id', $lines->pluck('id'))
                ->get(['pt.posting_id', 'x.code', 'pt.rate', 'pt.base_amount', 'pt.tax_amount'])->groupBy('posting_id');
            foreach ($lines as $l) {
                DB::table('pms_invoice_lines')->insert([
                    'invoice_id' => $id, 'posting_id' => $l->id, 'transaction_code_id' => $l->transaction_code_id, 'description' => $l->description,
                    'stay_date' => $l->stay_date ?? $l->business_date, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price,
                    'net_amount' => $l->net_amount, 'tax_amount' => $l->tax_amount, 'gross_amount' => $l->amount,
                    'tax_breakdown' => json_encode(($taxes[$l->id] ?? collect())->map(fn ($t) => ['code' => $t->code, 'rate' => (float) $t->rate, 'base' => (float) $t->base_amount, 'tax' => (float) $t->tax_amount])->values()),
                ]);
            }
            DB::table('pms_postings')->whereIn('id', $lines->pluck('id'))->update(['invoice_id' => $id]);

            return $id;
        });
    }

    /** KDV oranına göre toplam (fatura altı). @return list<array{rate: float, base: float, tax: float}> */
    public function taxSummary(int $invoiceId): array
    {
        $rows = [];
        foreach (DB::table('pms_invoice_lines')->where('invoice_id', $invoiceId)->get(['tax_breakdown', 'net_amount', 'tax_amount']) as $line) {
            $parts = json_decode((string) $line->tax_breakdown, true) ?: [['rate' => 0.0, 'base' => (float) $line->net_amount, 'tax' => (float) $line->tax_amount]];
            foreach ($parts as $part) {
                $key = number_format((float) $part['rate'], 2, '.', '');
                $rows[$key] ??= ['rate' => (float) $part['rate'], 'base' => 0.0, 'tax' => 0.0];
                $rows[$key]['base'] += (float) $part['base'];
                $rows[$key]['tax'] += (float) $part['tax'];
            }
        }
        ksort($rows, SORT_NUMERIC);

        return array_values(array_map(fn ($r) => ['rate' => $r['rate'], 'base' => round($r['base'], 2), 'tax' => round($r['tax'], 2)], $rows));
    }
}
