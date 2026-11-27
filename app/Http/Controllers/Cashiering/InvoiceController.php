<?php

namespace App\Http\Controllers\Cashiering;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\InvoiceService;
use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Folyo yazdırma (bilgi amaçlı döküm) ve fatura kesme / yazdırma. */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function folio(Folio $folio): View
    {
        $this->own($folio->property_id);
        $postings = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $folio->id)->whereNull('p.reversed_by_id')->whereNull('p.reversal_of_id')->orderBy('p.id')
            ->get(['p.id', 'p.business_date', 't.code', 'p.description', 'p.quantity', 'p.amount', 'p.foreign_currency', 'p.foreign_amount']);
        $taxRows = DB::table('pms_posting_taxes as pt')->whereIn('pt.posting_id', $postings->where('amount', '>', 0)->pluck('id'))
            ->groupBy('pt.rate')->orderBy('pt.rate')->selectRaw('pt.rate, SUM(pt.base_amount) AS base, SUM(pt.tax_amount) AS tax')->get();
        $reservation = $folio->reservation_id ? Reservation::with(['room', 'roomType', 'profile'])->find($folio->reservation_id) : null;

        return view('cashiering.print.folio', [
            'folio' => $folio, 'reservation' => $reservation, 'postings' => $postings, 'billTo' => $this->invoices->defaultBillTo($folio),
            'charges' => $postings->where('amount', '>', 0)->sum('amount'), 'payments' => -$postings->where('amount', '<', 0)->sum('amount'),
            'taxes' => $taxRows->map(fn ($t) => ['rate' => (float) $t->rate, 'base' => round((float) $t->base, 2), 'tax' => round((float) $t->tax, 2)])->all(),
            'currency' => app(Property::class)->base_currency, 'back' => $this->back($folio),
        ]);
    }

    public function issue(Request $request, Folio $folio): RedirectResponse
    {
        $this->own($folio->property_id);
        $data = $request->validate([
            'bill_to_name' => ['required', 'string', 'max:250'], 'bill_to_tax_office' => ['nullable', 'string', 'max:100'],
            'bill_to_tax_number' => ['nullable', 'string', 'max:30'], 'bill_to_address' => ['nullable', 'string', 'max:500'],
        ], [], ['bill_to_name' => __('pms.invoice.bill_to')]);
        try {
            $id = $this->invoices->issue($folio, ['name' => $data['bill_to_name'], 'tax_office' => $data['bill_to_tax_office'] ?? null,
                'tax_number' => $data['bill_to_tax_number'] ?? null, 'address' => $data['bill_to_address'] ?? null,
                'profile_id' => $this->invoices->defaultBillTo($folio)['profile_id']], $request->user()->id, $request->boolean('force'));
        } catch (PostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $id);
    }

    public function show(int $invoice): View
    {
        $row = DB::table('pms_invoices')->where('id', $invoice)->first();
        abort_unless($row, 404);
        $this->own($row->property_id);
        DB::table('pms_invoices')->where('id', $row->id)->increment('print_count');
        $folio = Folio::query()->findOrFail($row->folio_id);

        return view('cashiering.print.invoice', [
            'invoice' => $row, 'folio' => $folio, 'lines' => DB::table('pms_invoice_lines')->where('invoice_id', $row->id)->orderBy('id')->get(),
            'taxes' => $this->invoices->taxSummary($row->id),
            'reservation' => $row->reservation_id ? Reservation::with(['room', 'profile'])->find($row->reservation_id) : null,
            'issuer' => DB::table('users')->where('id', $row->issued_by)->value('username'), 'back' => $this->back($folio),
        ]);
    }

    private function back(Folio $folio): string
    {
        return $folio->reservation_id ? route('billing.reservation', [$folio->reservation_id, 'pencere' => $folio->window]) : route('billing.account', $folio);
    }

    private function own(int|string|null $propertyId): void
    {
        abort_unless((int) $propertyId === (int) app(Property::class)->id, 404);
    }
}
