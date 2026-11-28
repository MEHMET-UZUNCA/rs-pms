<?php

namespace App\Http\Controllers\Cashiering;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\CashierService;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cashiering → Posting (Fidelio Quick Posting): birden çok satır tek seferde; her satır oda no (içerideki misafirin 1. penceresi)
 * ya da hesap no (açık rezervasyonsuz hesap), departman kodu, adet, birim fiyat, açıklama / fiş no. Satırlar tek işlemde yazılır;
 * bir satır hatalıysa hiçbiri yazılmaz ve hatalı satırlar gösterilir.
 * Cashiering → Passer By: konaklamayan misafire hızlı hesap (folyo türü passer_by); satış, ödeme ve fatura folyo ekranında yapılır,
 * bakiye sıfırlanınca hesap kapatılır.
 */
class QuickPostingController extends Controller
{
    public const ROWS = 10;

    public function __construct(
        private readonly PostingService $postings,
        private readonly CashierService $cashiers,
        private readonly FolioService $folios,
    ) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);

        return view('cashiering.posting', [
            'codes' => TransactionCode::query()->where('property_id', $p->id)->where('is_active', true)->where('type', 'revenue')
                ->where('manual_post_allowed', true)->where('disabled_in_billing', false)->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'code', 'name', 'default_price']),
            'recent' => DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')->join('pms_folios as f', 'f.id', '=', 'p.folio_id')
                ->leftJoin('pms_rooms as r', 'r.id', '=', 'p.room_id')->where('p.property_id', $p->id)->where('p.posted_by', $request->user()->id)
                ->where('p.business_date', $p->current_business_date->toDateString())->where('p.source', 'quick_post')->orderByDesc('p.id')->limit(30)
                ->get(['p.created_at', 'r.number as room', 'f.folio_number', 'f.name as account', 't.code', 'p.description', 'p.quantity', 'p.amount', 'p.reference']),
            'rows' => self::ROWS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = app(Property::class);
        $lines = collect($request->input('lines', []))->map(fn ($l) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $l))
            ->filter(fn ($l) => ($l['target'] ?? '') !== '' || ($l['code'] ?? '') !== '' || ($l['amount'] ?? '') !== '')->values();
        if ($lines->isEmpty()) {
            return back()->with('error', __('pms.qpost.nothing'));
        }

        $errors = [];
        $resolved = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $amount = str_replace(',', '.', (string) ($l['amount'] ?? ''));
            $qty = str_replace(',', '.', (string) (($l['qty'] ?? '') ?: '1'));
            $code = TransactionCode::query()->where('property_id', $p->id)->where('code', (string) ($l['code'] ?? ''))->where('is_active', true)->first();
            $folio = $this->target($p->id, (string) ($l['target'] ?? ''));
            $msg = match (true) {
                ! $folio => __('pms.qpost.no_target', ['target' => $l['target'] ?? '']),
                ! $code || in_array($code->type, ['payment', 'deposit'], true) => __('pms.billing.unknown_code', ['code' => $l['code'] ?? '']),
                ! $code->manual_post_allowed || $code->disabled_in_billing => __('pms.billing.not_manual', ['code' => $code->code]),
                ! is_numeric($amount) || (float) $amount == 0.0 => __('pms.qpost.bad_amount'),
                ! is_numeric($qty) || (float) $qty <= 0 => __('pms.qpost.bad_qty'),
                default => null,
            };
            if ($msg) {
                $errors[$n] = $msg;

                continue;
            }
            $resolved[] = [$folio, $code, (float) $amount, (float) $qty, ($l['description'] ?? '') ?: null, ($l['reference'] ?? '') ?: null];
        }
        if ($errors) {
            return back()->withInput()->with('line_errors', $errors)
                ->with('error', __('pms.qpost.has_errors', ['lines' => implode(', ', array_keys($errors))]));
        }

        try {
            $total = DB::transaction(function () use ($resolved, $request, $p) {
                $shift = $this->cashiers->shiftFor($p->id, $request->user())->id;
                $sum = 0.0;
                foreach ($resolved as [$folio, $code, $amount, $qty, $description, $reference]) {
                    $sum += (float) $this->postings->charge($folio, $code, $amount, $qty, $description,
                        ['posted_by' => $request->user()->id, 'source' => 'quick_post', 'cashier_shift_id' => $shift, 'reference' => $reference])->amount;
                }

                return $sum;
            });
        } catch (PostingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cashiering.posting')->with('status', __('pms.qpost.done', ['count' => count($resolved), 'total' => number_format($total, 2, ',', '.')]));
    }

    /** Oda no → içerideki misafirin 1. penceresi; değilse açık rezervasyonsuz hesabın folyo numarası. */
    private function target(int $propertyId, string $value): ?Folio
    {
        if ($value === '') {
            return null;
        }
        $reservation = Reservation::query()->where('property_id', $propertyId)->where('status', 'in_house')
            ->whereHas('room', fn ($q) => $q->where('number', $value))->first();
        if ($reservation) {
            return $this->folios->window($reservation, 1);
        }

        return Folio::query()->where('property_id', $propertyId)->whereNull('reservation_id')->where('status', 'open')->where('folio_number', $value)->first();
    }

    // ------------------------------------------------------------ Passer By

    public function passerBy(Request $request): View
    {
        $p = app(Property::class);

        return view('cashiering.passer-by', [
            'accounts' => Folio::query()->where('property_id', $p->id)->where('type', 'passer_by')
                ->where(fn ($q) => $q->where('status', 'open')->orWhere('closed_business_date', $p->current_business_date->toDateString()))
                ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function openPasserBy(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $p = app(Property::class);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']]);
        $bd = $p->current_business_date->toDateString();
        $folio = Folio::create([
            'property_id' => $p->id, 'folio_number' => $numbers->next($p->id, 'folio'), 'type' => 'passer_by', 'window' => 1, 'status' => 'open',
            'name' => ($data['name'] ?? '') ?: __('pms.passer.default_name'), 'notes' => $data['notes'] ?? null,
            'valid_from' => $bd, 'valid_until' => $bd, 'actual_from' => $bd, 'opened_business_date' => $bd, 'opened_by' => $request->user()->id,
        ]);

        return redirect()->route('billing.account', $folio)->with('status', __('pms.passer.opened', ['number' => $folio->folio_number]));
    }

    /** Rezervasyonsuz hesabı kapat (Fidelio FA Check Out): bakiye sıfır olmalı. */
    public function closeAccount(Request $request, Folio $folio): RedirectResponse
    {
        abort_unless((int) $folio->property_id === (int) app(Property::class)->id && ! $folio->reservation_id, 404);
        if ($folio->status !== 'open') {
            return back()->with('error', __('pms.passer.already_closed'));
        }
        if (abs((float) $folio->balance) >= 0.005) {
            return back()->with('error', __('pms.passer.balance', ['balance' => number_format((float) $folio->balance, 2, ',', '.')]));
        }
        $bd = app(Property::class)->current_business_date->toDateString();
        $folio->update(['status' => 'closed', 'closed_business_date' => $bd, 'closed_by' => $request->user()->id, 'actual_until' => $bd]);

        return redirect()->route($folio->type === 'passer_by' ? 'cashiering.passer-by' : 'reservations.navigator', $folio->type === 'passer_by' ? [] : ['f' => 'fa'])
            ->with('status', __('pms.passer.closed', ['number' => $folio->folio_number]));
    }
}
