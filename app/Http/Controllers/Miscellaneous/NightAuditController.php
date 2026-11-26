<?php

namespace App\Http\Controllers\Miscellaneous;

use App\Domain\Core\Models\Property;
use App\Domain\NightAudit\Exceptions\NightAuditException;
use App\Domain\NightAudit\Services\NightAuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Gece Kapanışı (Fidelio "Night Audit"): kontrol listesi, amir onayıyla geçiş, adım adım çalıştırma, son kapanışın istatistik ve gelir özeti.
 */
class NightAuditController extends Controller
{
    public function __construct(private readonly NightAuditService $audit) {}

    public function index(): View
    {
        $property = app(Property::class);
        $runs = DB::table('pms_night_audit_runs as r')->leftJoin('users as u', 'u.id', '=', 'r.run_by')
            ->where('r.property_id', $property->id)->orderByDesc('r.business_date')->limit(10)
            ->get(['r.id', 'r.business_date', 'r.status', 'r.started_at', 'r.finished_at', 'r.exceptions_count', 'r.current_step', 'u.username']);
        $last = $runs->firstWhere('status', 'completed');
        $current = $runs->first(fn ($r) => $r->business_date === $property->current_business_date->toDateString() || str_starts_with((string) $r->business_date, $property->current_business_date->toDateString()));

        return view('miscellaneous.night-audit', [
            'businessDate' => $property->current_business_date,
            'checks' => $this->audit->preview($property->id),
            'runs' => $runs,
            'steps' => $current ? DB::table('pms_night_audit_steps')->where('run_id', $current->id)->orderBy('sequence')->get()->keyBy('code') : collect(),
            'current' => $current,
            'last' => $last,
            'stats' => $last ? DB::table('pms_daily_statistics')->where('property_id', $property->id)->where('business_date', $last->business_date)->where('dimension', 'total')->first() : null,
            'revenue' => $last ? DB::table('pms_daily_revenue as r')->join('pms_transaction_codes as t', 't.id', '=', 'r.transaction_code_id')
                ->where('r.property_id', $property->id)->where('r.business_date', $last->business_date)
                ->groupBy('t.code', 't.name')->orderByDesc(DB::raw('SUM(r.gross_amount)'))
                ->selectRaw('t.code, t.name, SUM(r.postings_count) AS postings, SUM(r.net_amount) AS net, SUM(r.tax_amount) AS tax, SUM(r.gross_amount) AS gross')->get() : collect(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $property = app(Property::class);
        $closing = $property->current_business_date->format('d.m.Y');
        $reasons = array_filter((array) $request->input('override', []), fn ($v) => trim((string) $v) !== '');
        if ($reasons && ! Gate::allows('permission', 'night_audit.override')) {
            abort(403, __('pms.audit.no_override'));
        }
        $acknowledged = array_map(fn ($v) => mb_substr(trim((string) $v), 0, 200).' — '.$request->user()->username, $reasons);

        try {
            $this->audit->run($property->id, $request->user()->id, $acknowledged);
        } catch (NightAuditException $e) {
            return back()->with('error', __('pms.audit.held').' '.$e->getMessage());
        }

        return redirect()->route('night-audit.index')->with('status', __('pms.audit.completed', [
            'closed' => $closing, 'next' => $property->fresh()->current_business_date->format('d.m.Y'),
        ]));
    }
}
