<?php

namespace App\Http\Controllers;

use App\Domain\Core\Models\Property;
use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportCatalog;
use App\Support\BusinessDateInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Miscellaneous → Reports (Ctrl+Shift+R; docs/10-raporlar.md). Solda yetkiye göre rapor ağacı ve arama, sağda süzgeçler;
 * F10 göster, yazdır (A4, geniş raporlar yatay) ve Excel için CSV. Her rapor grubunun yetkisi ayrı (reports.*).
 */
class ReportController extends Controller
{
    /** Kullanıcı en az bir rapor grubunu görebiliyor mu (menü). */
    public static function canSee(): bool
    {
        return collect(ReportCatalog::GROUPS)->contains(fn ($g) => Gate::allows('permission', ReportCatalog::permission($g)));
    }

    public function index(Request $request, ReportBuilder $builder): mixed
    {
        $allowed = collect(ReportCatalog::REPORTS)->filter(fn ($r) => Gate::allows('permission', ReportCatalog::permission($r['group'])));
        abort_if($allowed->isEmpty(), 403);
        $key = (string) $request->query('r', '');
        $report = $allowed[$key] ?? null;
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();

        $filters = null;
        $result = null;
        if ($report) {
            $hasRange = in_array('range', $report['filters'], true);
            $from = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? ($hasRange && in_array($report['group'], ['finance'], true) ? $bd->startOfMonth() : $bd);
            $to = $hasRange ? (BusinessDateInput::parse((string) $request->query('bit', ''), $bd) ?? ($report['group'] === 'finance' ? $bd : $from->addDays(13))) : $from;
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }
            if ($from->diffInDays($to) > 366) {
                $to = $from->addDays(366);
            }
            $filters = ['from' => $from, 'to' => $to, 'room_type' => (int) $request->query('tip') ?: null, 'user' => (int) $request->query('kullanici') ?: null,
                'unassigned' => $request->boolean('atanmamis'), 'clean' => $request->boolean('temiz')];
            if ($request->has('goster') || $request->has('format')) {
                $result = $builder->run($key, $p, $filters);
            }
        }

        // Gece kapanışı arşivi: kapanan günün haliyle saklanmış rapor (değişmez)
        $archive = null;
        if ($request->filled('arsiv')) {
            $date = BusinessDateInput::parse((string) $request->query('arsiv'), $bd);
            $run = $date ? DB::table('pms_night_audit_runs')->where('property_id', $p->id)->where('business_date', $date->toDateString())->where('status', 'completed')->first() : null;
            $pack = $run ? json_decode((string) $run->report_pack, true) : null;
            $archive = ['date' => $date, 'found' => (bool) $pack, 'saved_at' => $pack['saved_at'] ?? $run?->finished_at, 'keys' => array_keys($pack['reports'] ?? [])];
            $result = $report ? ($pack['reports'][$key] ?? null) : null;
            if ($report && $date) {
                $filters = array_merge($filters ?? [], ['from' => $date, 'to' => $date]);
            }
        }

        $format = (string) $request->query('format', '');
        if ($result && $format === 'csv') {
            return $this->csv($key, $result);
        }
        $view = $result && $format === 'print' ? 'reports.print' : 'reports.index';

        return view($view, [
            'tree' => $allowed->groupBy(fn ($r) => $r['group'], true)->sortBy(fn ($r, $g) => array_search($g, ReportCatalog::GROUPS, true)),
            'key' => $report ? $key : null, 'report' => $report, 'filters' => $filters, 'result' => $result, 'archive' => $archive,
            'archiveDates' => DB::table('pms_night_audit_runs')->where('property_id', $p->id)->where('status', 'completed')->orderByDesc('business_date')->limit(14)->pluck('business_date'),
            'roomTypes' => DB::table('pms_room_types')->where('property_id', $p->id)->orderBy('sort')->orderBy('code')->get(['id', 'code', 'name']),
            'users' => DB::table('users')->orderBy('username')->get(['id', 'username']),
            'base' => $p->base_currency,
        ]);
    }

    /** @param array{columns: list<array{0: string, 1: string}>, rows: list<array<string, mixed>>, totals: ?array<string, mixed>} $result */
    private function csv(string $key, array $result): StreamedResponse
    {
        $name = 'rapor-'.$key.'-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // Excel için UTF-8 işareti
            $cols = $result['columns'];
            fputcsv($out, array_map(fn ($c) => __('pms.reports.col.'.$c[0]), $cols), ';', '"', '');
            foreach (array_merge($result['rows'], $result['totals'] ? [$result['totals']] : []) as $row) {
                fputcsv($out, array_map(function ($c) use ($row) {
                    $v = $row[$c[0]] ?? '';
                    $type = $c[1] === 'row' ? ($row['type'] ?? 'money') : $c[1];

                    return is_float($v) || (is_int($v) && in_array($type, ['money', 'pct'], true)) ? number_format((float) $v, $type === 'pct' ? 1 : 2, ',', '') : (string) $v;
                }, $cols), ';', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
