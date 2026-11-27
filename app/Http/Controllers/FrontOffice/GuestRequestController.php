<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Domain\FrontOffice\Services\GuestRequestService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Front Desk → Guest Requests: misafir istekleri ve şikâyetler (görev listesi, yeni kayıt, durum, not, geçmiş). */
class GuestRequestController extends Controller
{
    public const TABS = ['open' => 'alt+1', 'overdue' => 'alt+2', 'mine' => 'alt+3', 'done' => 'alt+4', 'all' => 'alt+5'];

    public function __construct(private readonly GuestRequestService $requests) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $tab = array_key_exists((string) $request->query('sekme'), self::TABS) ? $request->query('sekme') : 'open';
        $department = in_array($request->query('departman'), GuestRequestService::DEPARTMENTS, true) ? $request->query('departman') : null;
        $now = now();
        $overdue = fn ($q) => $q->whereIn('t.status', ['open', 'in_progress'])->where(fn ($w) => $w->where('t.trace_date', '<', $now->toDateString())
            ->orWhere(fn ($x) => $x->where('t.trace_date', $now->toDateString())->where('t.trace_time', '<', $now->format('H:i:s'))));

        $rows = DB::table('pms_traces as t')->leftJoin('pms_rooms as r', 'r.id', '=', 't.room_id')->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.property_id', $p->id)->whereNotNull('t.status')
            ->when($department, fn ($q) => $q->where('t.department', $department))
            ->when($tab === 'open', fn ($q) => $q->whereIn('t.status', ['open', 'in_progress']))
            ->when($tab === 'overdue', $overdue)
            ->when($tab === 'mine', fn ($q) => $q->where('t.created_by', $request->user()->id)->whereIn('t.status', ['open', 'in_progress']))
            ->when($tab === 'done', fn ($q) => $q->whereIn('t.status', ['done', 'cancelled'])->where('t.updated_at', '>=', $now->copy()->subDays(7)))
            ->orderByRaw("CASE t.priority WHEN 'high' THEN 0 ELSE 1 END")->orderBy('t.trace_date')->orderBy('t.trace_time')->limit(500)
            ->get(['t.*', 'r.number as room', 'u.username']);

        return view('front-office.requests', [
            'rows' => $rows, 'tab' => $tab, 'department' => $department, 'selected' => (int) session('selected', 0),
            'counts' => ['open' => DB::table('pms_traces as t')->where('t.property_id', $p->id)->whereIn('t.status', ['open', 'in_progress'])->count(),
                'overdue' => $this->requests->overdueCount($p->id)],
            'defaultDue' => $now->copy()->addMinutes(30),
        ]);
    }

    public function show(int $trace): View
    {
        $row = $this->own($trace);

        return view('front-office._request', [
            'trace' => $row, 'room' => $row->room_id ? DB::table('pms_rooms')->where('id', $row->room_id)->value('number') : null,
            'logs' => DB::table('pms_trace_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->where('l.trace_id', $row->id)->orderBy('l.id')->get(['l.*', 'u.username']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $p = app(Property::class);
        $date = BusinessDateInput::parse((string) $request->input('due_date'), now()->toImmutable()) ?? now()->toImmutable();
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:10'], 'guest_name' => ['nullable', 'string', 'max:150'],
            'kind' => ['required', Rule::in(GuestRequestService::KINDS)], 'department' => ['required', Rule::in(GuestRequestService::DEPARTMENTS)],
            'priority' => ['required', Rule::in(['normal', 'high'])], 'due_time' => ['required', 'date_format:H:i'], 'message' => ['required', 'string', 'max:1000'],
        ], [], ['message' => __('pms.req.message'), 'due_time' => __('pms.req.due')]);
        try {
            $id = $this->requests->create($p->id, $data + ['due_at' => $date->toDateString().' '.$data['due_time']], $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('requests.index')->with('status', __('pms.req.created'))->with('selected', $id);
    }

    public function status(Request $request, int $trace): RedirectResponse
    {
        $row = $this->own($trace);
        $data = $request->validate(['status' => ['required', Rule::in(GuestRequestService::STATUSES)], 'note' => ['nullable', 'string', 'max:1000']]);
        try {
            $this->requests->changeStatus($row->id, $data['status'], $data['note'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $row->id);
        }

        return back()->with('status', __('pms.req.status_saved', ['status' => __('pms.req.statuses.'.$data['status'])]))->with('selected', $row->id);
    }

    public function note(Request $request, int $trace): RedirectResponse
    {
        $row = $this->own($trace);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $this->requests->note($row->id, $data['note'], $request->user()->id);

        return back()->with('status', __('pms.req.note_saved'))->with('selected', $row->id);
    }

    private function own(int $id): object
    {
        $row = DB::table('pms_traces')->where('id', $id)->where('property_id', app(Property::class)->id)->whereNotNull('status')->first();
        abort_unless($row, 404);

        return $row;
    }
}
