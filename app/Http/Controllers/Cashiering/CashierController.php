<?php

namespace App\Http\Controllers\Cashiering;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Services\CashierService;
use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cashiering → Close Cashier: kullanıcının açık vardiyası (ödeme kodu × döviz sistem toplamı, sayılan tutar, fark), kapanış ve rapor;
 * son vardiyalar listesi (cashiering.shifts yetkisiyle bütün kasiyerler).
 */
class CashierController extends Controller
{
    public function __construct(private readonly CashierService $cashiers) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $shift = $this->cashiers->openShift($p->id, $request->user());
        $all = Gate::allows('permission', 'cashiering.shifts');
        $recent = DB::table('pms_cashier_shifts as s')->join('pms_cashiers as c', 'c.id', '=', 's.cashier_id')->where('s.property_id', $p->id)
            ->when(! $all, fn ($q) => $q->where('c.user_id', $request->user()->id))
            ->orderByDesc('s.id')->limit(30)->get(['s.*', 'c.number', 'c.name']);
        $differences = DB::table('pms_cashier_shift_totals')->whereIn('shift_id', $recent->pluck('id'))->groupBy('shift_id')
            ->selectRaw('shift_id, SUM(ABS(COALESCE(difference, 0))) AS diff')->pluck('diff', 'shift_id');

        return view('cashiering.cashier', [
            'shift' => $shift, 'totals' => $shift ? $this->cashiers->totals($shift) : collect(), 'charges' => $shift ? $this->cashiers->charges($shift) : collect(),
            'recent' => $recent, 'differences' => $differences, 'all' => $all,
            'cashier' => DB::table('pms_cashiers')->where('property_id', $p->id)->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $shift = $this->cashiers->openShift(app(Property::class)->id, $request->user());
        abort_unless($shift, 404);
        $data = $request->validate(['counted' => ['nullable', 'array'], 'counted.*' => ['nullable', 'regex:/^-?\d{1,9}([.,]\d{1,2})?$/'], 'notes' => ['nullable', 'string', 'max:500']],
            [], ['counted.*' => __('pms.cashier.counted')]);
        try {
            $this->cashiers->close($shift, $data['counted'] ?? [], $data['notes'] ?? null, $request->user()->id);
        } catch (PostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cashier.report', $shift->id)->with('status', __('pms.cashier.closed_ok'));
    }

    public function report(Request $request, int $shift): View
    {
        $row = DB::table('pms_cashier_shifts')->where('id', $shift)->first();
        abort_unless($row && (int) $row->property_id === (int) app(Property::class)->id, 404);
        $cashier = DB::table('pms_cashiers')->where('id', $row->cashier_id)->first();
        abort_unless((int) $cashier->user_id === (int) $request->user()->id || Gate::allows('permission', 'cashiering.shifts'), 403);

        return view('cashiering.print.shift', [
            'shift' => $row, 'cashier' => $cashier, 'totals' => $this->cashiers->totals($row), 'charges' => $this->cashiers->charges($row),
            'closedTotals' => DB::table('pms_cashier_shift_totals')->where('shift_id', $row->id)->get()->keyBy(fn ($t) => $t->transaction_code_id.'|'.$t->currency_code),
        ]);
    }
}
