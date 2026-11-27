<?php

namespace App\Http\Controllers\Cashiering;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Models\Posting;
use App\Domain\Cashiering\Services\CashierService;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Cashiering\Services\InvoiceService;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\ExchangeRateService;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Folyo (Fidelio "Billing"): rezervasyonun 8 penceresi ya da rezervasyonsuz hesap (FA). Kayıt at, ödeme, düzeltme (ters kayıt),
 * pencereler arası transfer, check-out. Bütün kayıtlar PostingService üzerinden (vergi kırılımı, kapalı gün/folyo kontrolü).
 */
class BillingController extends Controller
{
    public function __construct(private readonly PostingService $postings, private readonly FolioService $folios, private readonly CashierService $cashiers) {}

    public function reservation(Request $request, Reservation $reservation): View
    {
        $this->own($reservation->property_id);
        $reservation->load(['profile', 'room', 'roomType', 'rateCode']);
        $windows = Folio::where('reservation_id', $reservation->id)->get()->keyBy('window');
        $current = max(1, min(8, (int) $request->query('pencere', 1)));

        return $this->page($request, $reservation, $windows, $current, $windows->get($current));
    }

    public function account(Request $request, Folio $folio): View
    {
        $this->own($folio->property_id);
        if ($folio->reservation_id) {
            abort(404);
        }

        return $this->page($request, null, collect([1 => $folio]), 1, $folio);
    }

    public function post(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'], 'amount' => ['required', 'numeric', 'not_in:0', 'min:-99999999', 'max:99999999'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:9999'], 'description' => ['nullable', 'string', 'max:100'],
        ], [], ['code' => __('pms.billing.department_code'), 'amount' => __('pms.billing.unit_price'), 'quantity' => __('pms.billing.qty')]);

        $code = TransactionCode::where('property_id', app(Property::class)->id)->where('code', trim($data['code']))->where('is_active', true)->first();
        if (! $code || in_array($code->type, ['payment', 'deposit'], true)) {
            return back()->withInput()->withErrors(['code' => __('pms.billing.unknown_code', ['code' => $data['code']])]);
        }
        if (! $code->manual_post_allowed || $code->disabled_in_billing) {
            return back()->withInput()->withErrors(['code' => __('pms.billing.not_manual', ['code' => $code->code])]);
        }

        return $this->run($request, function (Folio $folio) use ($code, $data, $request) {
            $posting = $this->postings->charge($folio, $code, (float) $data['amount'], (float) ($data['quantity'] ?? 1), ($data['description'] ?? null) ?: null,
                ['posted_by' => $request->user()->id, 'source' => 'manual', 'cashier_shift_id' => $this->cashiers->shiftFor($folio->property_id, $request->user())->id]);

            return __('pms.billing.posted', ['code' => $code->code, 'name' => $code->name, 'amount' => self::money($posting->amount)]);
        });
    }

    /**
     * Ödeme. Döviz seçilirse tutar o dövizdedir: nakitte geçerli günün Cash Rate'i (yoksa Posting Rate), diğerlerinde Posting Rate ile
     * TL'ye çevrilir; döviz tutarı ve kur kayıtta saklanır (kasa kapanışında döviz olarak sayılır).
     */
    public function payment(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        $property = app(Property::class);
        $data = $request->validate([
            'payment_code_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'not_in:0', 'min:-99999999', 'max:99999999'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:pms_currencies,code'], 'description' => ['nullable', 'string', 'max:100'],
        ], [], ['payment_code_id' => __('pms.billing.payment_code'), 'amount' => __('pms.billing.amount')]);
        $code = TransactionCode::where('property_id', $property->id)->whereKey($data['payment_code_id'])->whereIn('type', ['payment', 'deposit'])->where('is_active', true)->firstOrFail();
        $currency = strtoupper($data['currency_code'] ?? '') ?: ($code->currency_code ?: $property->base_currency);
        $attributes = ['posted_by' => $request->user()->id];
        $amount = (float) $data['amount'];
        $label = self::money($amount);
        if ($currency !== $property->base_currency) {
            $rate = $rates->current($property->id, $currency);
            $value = $rate ? (float) (in_array($code->payment_kind, ['cash', 'foreign_cash'], true) ? ($rate->cash_rate ?: $rate->posting_rate) : $rate->posting_rate) : 0.0;
            if ($value <= 0) {
                return back()->withInput()->withErrors(['currency_code' => __('pms.billing.no_rate', ['currency' => $currency])]);
            }
            $attributes += ['foreign_currency' => $currency, 'foreign_amount' => -round($amount, 2), 'foreign_unit_price' => round($amount, 2), 'exchange_rate' => $value];
            $label = $currency.' '.self::money($amount).' × '.number_format($value, 4, ',', '.');
            $data['description'] = ($data['description'] ?? null) ?: $code->name.' '.$label;
            $amount = round($amount * $value, 2);
        }

        return $this->run($request, function (Folio $folio) use ($code, $data, $request, $amount, $attributes, $label) {
            $this->postings->payment($folio, $code, $amount, ($data['description'] ?? null) ?: null,
                $attributes + ['cashier_shift_id' => $this->cashiers->shiftFor($folio->property_id, $request->user())->id]);

            return __('pms.billing.paid', ['amount' => $label.' ('.$code->name.')']);
        });
    }

    public function correct(Request $request, Posting $posting): RedirectResponse
    {
        $this->own($posting->property_id);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:200']])['note'] ?? null;
        try {
            $this->postings->reverse($posting, null, $note, $request->user()->id);
        } catch (PostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.billing.reversed_ok'));
    }

    public function transfer(Request $request, Posting $posting): RedirectResponse
    {
        $this->own($posting->property_id);
        $window = (int) $request->validate(['window' => ['required', 'integer', 'min:1', 'max:8']])['window'];
        $reservation = $posting->folio?->reservation;
        if (! $reservation) {
            return back()->with('error', __('pms.billing.transfer').': —');
        }
        try {
            $this->postings->transfer($posting, $this->folios->window($reservation, $window), $request->user()->id);
        } catch (PostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.billing.transferred', ['n' => $window]));
    }

    public function checkOut(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->own($reservation->property_id);
        try {
            $service->checkOut($reservation, $request->user()->id);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('reservations.navigator', ['f' => 'cikmis'])
            ->with('status', __('pms.billing.checked_out', ['name' => $reservation->profile?->display_name]));
    }

    /** Hedef folyo: FA ise folio_id, rezervasyonsa rezervasyon + pencere (pencere yoksa açılır). */
    private function run(Request $request, \Closure $action): RedirectResponse
    {
        $target = $request->validate(['folio_id' => ['nullable', 'integer'], 'reservation_id' => ['nullable', 'integer'], 'window' => ['nullable', 'integer', 'min:1', 'max:8']]);
        try {
            $message = DB::transaction(function () use ($target, $action) {
                if (! empty($target['reservation_id'])) {
                    $reservation = Reservation::findOrFail($target['reservation_id']);
                    $this->own($reservation->property_id);
                    $folio = $this->folios->window($reservation, (int) ($target['window'] ?? 1));
                } else {
                    $folio = Folio::findOrFail($target['folio_id'] ?? 0);
                    $this->own($folio->property_id);
                }

                return $action($folio);
            });
        } catch (PostingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', $message);
    }

    private function page(Request $request, ?Reservation $reservation, $windows, int $current, ?Folio $folio): View
    {
        $showReversed = $request->boolean('duzeltmeler');
        $postings = $folio ? DB::table('pms_postings as p')
            ->join('pms_transaction_codes as tc', 'tc.id', '=', 'p.transaction_code_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.posted_by')
            ->where('p.folio_id', $folio->id)
            ->when(! $showReversed, fn ($q) => $q->whereNull('p.reversed_by_id')->whereNull('p.reversal_of_id'))
            ->orderBy('p.id')
            ->get(['p.id', 'p.business_date', 'tc.code', 'tc.type', 'p.description', 'p.quantity', 'p.amount', 'p.net_amount', 'p.tax_amount',
                'p.reference', 'p.reversed_by_id', 'p.reversal_of_id', 'p.source', 'u.username', 'p.created_at']) : collect();
        $p = app(Property::class)->id;

        return view('cashiering.billing', [
            'reservation' => $reservation, 'windows' => $windows, 'current' => $current, 'folio' => $folio, 'postings' => $postings,
            'showReversed' => $showReversed,
            'paymentCodes' => TransactionCode::where('property_id', $p)->whereIn('type', ['payment', 'deposit'])->where('is_active', true)
                ->where('disabled_in_payments', false)->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name']),
            'currencies' => DB::table('pms_exchange_rates as e')->where('e.property_id', $p)->where('e.rate_type', 'day')->distinct()->orderBy('e.currency_code')->pluck('e.currency_code'),
            'baseCurrency' => app(Property::class)->base_currency,
            'billTo' => $folio ? app(InvoiceService::class)->defaultBillTo($folio) : null,
            'accountReason' => $folio ? app(InvoiceService::class)->accountReason($folio) : null,
            'invoices' => $folio ? DB::table('pms_invoices')->where('folio_id', $folio->id)->orderBy('id')->get(['id', 'invoice_number', 'total_gross', 'status']) : collect(),
            'pendingCount' => ($pending = $folio ? app(InvoiceService::class)->pending($folio) : collect())->count(), 'pendingAmount' => (float) $pending->sum('amount'),
            'manualCodes' => TransactionCode::where('property_id', $p)->whereNotIn('type', ['payment', 'deposit'])->where('is_active', true)
                ->where('manual_post_allowed', true)->where('disabled_in_billing', false)->orderByRaw('CAST(code AS UNSIGNED)')->get(['code', 'name']),
        ]);
    }

    private function own(int|string|null $propertyId): void
    {
        abort_unless((int) $propertyId === (int) app(Property::class)->id, 404);
    }

    private static function money($v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }
}
