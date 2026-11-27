<?php

namespace App\Domain\Reports;

use App\Domain\Core\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raporların verisi. Her yöntem ['columns' => [[kolon, tür]], 'rows' => list<array>, 'totals' => ?array] döner.
 * Tür: text, int, money, pct, date. Tutarlar tesisin ana para birimindedir (dövizli gece fiyatı fiyat kodu dövizinde, kolonda belirtilir).
 */
class ReportBuilder
{
    /** Müsaitlikten düşen (kesin) rezervasyon durumları. */
    private const SOLD = ['confirmed', 'in_house', 'checked_out'];

    private Property $property;

    /** @var array{from: CarbonImmutable, to: CarbonImmutable, room_type: ?int, user: ?int, unassigned: bool, clean: bool} */
    private array $f;

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, room_type?: ?int, user?: ?int, unassigned?: bool, clean?: bool}  $filters
     * @return array{columns: list<array{0: string, 1: string}>, rows: list<array<string, mixed>>, totals: ?array<string, mixed>}
     */
    public function run(string $key, Property $property, array $filters): array
    {
        $this->property = $property;
        $this->f = $filters + ['room_type' => null, 'user' => null, 'unassigned' => false, 'clean' => false];

        return $this->{ReportCatalog::REPORTS[$key]['method']}();
    }

    // ------------------------------------------------------------ Ön büro

    private function arrivals(): array
    {
        $date = $this->f['from']->toDateString();
        $rows = $this->reservations()->where('r.arrival_date', $date)->whereIn('r.status', ['confirmed', 'tentative', 'in_house', 'waitlist'])
            ->when($this->f['unassigned'], fn ($q) => $q->whereNull('r.room_id'))
            ->orderBy('rm.number')->orderBy('p.display_name')
            ->get(['r.confirmation_number', 'rm.number as room', 'p.display_name as guest', 'r.nights', 'rt.code as room_type', 'r.adults', 'r.children',
                'rc.code as rate_code', 'r.currency_code', 'r.eta', 'r.status', 'r.guest_comments', 'ag.display_name as agent', 'co.display_name as company', 'r.id']);
        $first = DB::table('pms_reservation_nights')->whereIn('reservation_id', $rows->pluck('id'))->where('stay_date', $date)->pluck('amount', 'reservation_id');

        return $this->result([['room', 'text'], ['guest', 'text'], ['confirmation', 'text'], ['eta', 'text'], ['nights', 'int'], ['room_type', 'text'], ['persons', 'text'],
            ['rate_code', 'text'], ['rate', 'money'], ['currency', 'text'], ['agent_company', 'text'], ['status', 'text'], ['comment', 'text']],
            $rows->map(fn ($r) => ['room' => $r->room, 'guest' => $r->guest, 'confirmation' => $r->confirmation_number, 'eta' => $r->eta ? substr($r->eta, 0, 5) : '',
                'nights' => (int) $r->nights, 'room_type' => $r->room_type, 'persons' => $r->adults.($r->children ? '+'.$r->children : ''), 'rate_code' => $r->rate_code,
                'rate' => (float) ($first[$r->id] ?? 0), 'currency' => $r->currency_code, 'agent_company' => $r->agent ?? $r->company,
                'status' => __('pms.res.statuses.'.$r->status), 'comment' => $r->guest_comments]),
            ['room' => __('pms.reports.total'), 'guest' => $rows->count(), 'nights' => $rows->sum('nights'), 'persons' => $rows->sum('adults').'+'.$rows->sum('children')]);
    }

    private function departures(): array
    {
        $date = $this->f['from']->toDateString();
        $rows = $this->reservations()->where('r.departure_date', $date)->whereIn('r.status', ['in_house', 'checked_out'])
            ->orderBy('rm.number')->get(['r.id', 'rm.number as room', 'p.display_name as guest', 'r.arrival_date', 'r.status', 'r.checked_out_at', 'r.adults', 'r.children']);
        $balances = $this->balances($rows->pluck('id'));

        return $this->result([['room', 'text'], ['guest', 'text'], ['arrival', 'date'], ['persons', 'text'], ['status', 'text'], ['checked_out', 'text'], ['balance', 'money']],
            $rows->map(fn ($r) => ['room' => $r->room, 'guest' => $r->guest, 'arrival' => $r->arrival_date, 'persons' => $r->adults.($r->children ? '+'.$r->children : ''),
                'status' => __('pms.res.statuses.'.$r->status), 'checked_out' => $r->checked_out_at ? substr((string) $r->checked_out_at, 11, 5) : '', 'balance' => $balances[$r->id] ?? 0.0]),
            ['room' => __('pms.reports.total'), 'guest' => $rows->count(), 'balance' => round($balances->sum(), 2)]);
    }

    private function inHouse(): array
    {
        $rows = $this->reservations()->where('r.status', 'in_house')->orderBy('rm.number')
            ->leftJoin('pms_countries as c', 'c.code', '=', 'p.nationality_code')
            ->get(['r.id', 'rm.number as room', 'rt.code as room_type', 'p.display_name as guest', 'p.nationality_code', 'r.adults', 'r.children', 'r.arrival_date',
                'r.departure_date', 'rc.code as rate_code', 'r.currency_code', 'ag.display_name as agent', 'co.display_name as company']);
        $today = DB::table('pms_reservation_nights')->whereIn('reservation_id', $rows->pluck('id'))->where('stay_date', $this->property->current_business_date->toDateString())->pluck('amount', 'reservation_id');
        $balances = $this->balances($rows->pluck('id'));

        return $this->result([['room', 'text'], ['room_type', 'text'], ['guest', 'text'], ['nationality', 'text'], ['persons', 'text'], ['arrival', 'date'], ['departure', 'date'],
            ['rate_code', 'text'], ['rate', 'money'], ['currency', 'text'], ['agent_company', 'text'], ['balance', 'money']],
            $rows->map(fn ($r) => ['room' => $r->room, 'room_type' => $r->room_type, 'guest' => $r->guest, 'nationality' => $r->nationality_code, 'persons' => $r->adults.($r->children ? '+'.$r->children : ''),
                'arrival' => $r->arrival_date, 'departure' => $r->departure_date, 'rate_code' => $r->rate_code, 'rate' => (float) ($today[$r->id] ?? 0), 'currency' => $r->currency_code,
                'agent_company' => $r->agent ?? $r->company, 'balance' => $balances[$r->id] ?? 0.0]),
            ['room' => __('pms.reports.total'), 'guest' => $rows->count(), 'persons' => $rows->sum('adults').'+'.$rows->sum('children'), 'balance' => round($balances->sum(), 2)]);
    }

    private function cancellations(): array
    {
        [$from, $to] = [$this->f['from']->toDateString(), $this->f['to']->toDateString()];
        $rows = $this->reservations()->leftJoin('pms_reason_codes as rs', 'rs.id', '=', 'r.cancel_reason_code_id')->leftJoin('users as u', 'u.id', '=', 'r.cancelled_by')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('r.status', 'cancelled')->whereBetween('r.cancelled_at', [$from.' 00:00:00', $to.' 23:59:59']))
                ->orWhere(fn ($q) => $q->where('r.status', 'no_show')->whereBetween('r.arrival_date', [$from, $to])))
            ->orderBy('r.arrival_date')->get(['r.status', 'r.confirmation_number', 'p.display_name as guest', 'r.arrival_date', 'r.nights', 'rt.code as room_type', 'r.cancelled_at',
                'r.cancellation_number', 'rs.name as reason', 'u.username', 'ag.display_name as agent', 'r.total_room_amount']);

        return $this->result([['status', 'text'], ['confirmation', 'text'], ['guest', 'text'], ['arrival', 'date'], ['nights', 'int'], ['room_type', 'text'], ['agent_company', 'text'],
            ['cancelled_at', 'text'], ['reason', 'text'], ['user', 'text'], ['revenue', 'money']],
            $rows->map(fn ($r) => ['status' => __('pms.res.statuses.'.$r->status), 'confirmation' => $r->confirmation_number.($r->cancellation_number ? ' / '.$r->cancellation_number : ''),
                'guest' => $r->guest, 'arrival' => $r->arrival_date, 'nights' => (int) $r->nights, 'room_type' => $r->room_type, 'agent_company' => $r->agent,
                'cancelled_at' => $r->cancelled_at ? CarbonImmutable::parse($r->cancelled_at)->format('d.m.Y H:i') : '', 'reason' => $r->reason, 'user' => $r->username,
                'revenue' => (float) $r->total_room_amount]),
            ['status' => __('pms.reports.total'), 'guest' => $rows->count(), 'nights' => $rows->sum('nights'), 'revenue' => round($rows->sum('total_room_amount'), 2)]);
    }

    private function nationality(): array
    {
        $rows = DB::table('pms_reservations as r')->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->leftJoin('pms_countries as c', 'c.code', '=', 'p.nationality_code')
            ->where('r.property_id', $this->property->id)->where('r.status', 'in_house')
            ->groupBy('p.nationality_code', 'c.name_en', 'c.name_tr')
            ->selectRaw('p.nationality_code, c.name_en, c.name_tr, COUNT(*) AS rooms, SUM(r.adults) AS adults, SUM(r.children) AS children')
            ->orderByDesc('rooms')->get();
        $total = max(1, (int) $rows->sum('adults') + (int) $rows->sum('children'));

        return $this->result([['nationality', 'text'], ['country', 'text'], ['rooms', 'int'], ['adults', 'int'], ['children', 'int'], ['share', 'pct']],
            $rows->map(fn ($r) => ['nationality' => $r->nationality_code ?? '—', 'country' => app()->getLocale() === 'tr' ? $r->name_tr : $r->name_en, 'rooms' => (int) $r->rooms,
                'adults' => (int) $r->adults, 'children' => (int) $r->children, 'share' => round(((int) $r->adults + (int) $r->children) * 100 / $total, 1)]),
            ['nationality' => __('pms.reports.total'), 'rooms' => (int) $rows->sum('rooms'), 'adults' => (int) $rows->sum('adults'), 'children' => (int) $rows->sum('children'), 'share' => 100.0]);
    }

    // ------------------------------------------------------------ Odalar ve kat hizmetleri

    private function freeRooms(): array
    {
        [$from, $to] = [$this->f['from']->toDateString(), $this->f['to']->toDateString()];
        $busy = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->whereBetween('n.stay_date', [$from, $to])->whereNotNull('n.room_id')->where('n.status', '<>', 'cancelled')
            ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->select('n.room_id');
        $closed = DB::table('pms_room_outages')->where('date_from', '<=', $to)->where('date_to', '>=', $from)->whereNull('released_at')->select('room_id');
        $rows = $this->rooms()->whereNotIn('rm.id', $busy)->whereNotIn('rm.id', $closed)
            ->when($this->f['clean'], fn ($q) => $q->whereIn('rm.hk_status', ['clean', 'inspected']))
            ->get(['rm.id', 'rm.number', 'rt.code as room_type', 'fl.name as floor', 'rm.hk_status', 'rm.fo_status']);
        $features = DB::table('pms_room_feature_room as a')->join('pms_room_features as f', 'f.id', '=', 'a.room_feature_id')->whereIn('a.room_id', $rows->pluck('id'))
            ->get(['a.room_id', 'f.code'])->groupBy('room_id');

        return $this->result([['room', 'text'], ['room_type', 'text'], ['floor', 'text'], ['hk_status', 'text'], ['fo_status', 'text'], ['features', 'text']],
            $rows->map(fn ($r) => ['room' => $r->number, 'room_type' => $r->room_type, 'floor' => $r->floor, 'hk_status' => __('pms.ooo.hk.'.$r->hk_status),
                'fo_status' => __('pms.history.fo.'.$r->fo_status), 'features' => ($features[$r->id] ?? collect())->pluck('code')->implode(', ')]),
            ['room' => __('pms.reports.total'), 'room_type' => $rows->count()]);
    }

    private function roomStatus(): array
    {
        $date = $this->property->current_business_date->toDateString();
        $rows = $this->rooms()->leftJoin('pms_reservations as r', 'r.id', '=', 'rm.current_reservation_id')->leftJoin('pms_profiles as p', 'p.id', '=', 'r.profile_id')
            ->get(['rm.id', 'rm.number', 'rt.code as room_type', 'fl.name as floor', 'rm.fo_status', 'rm.hk_status', 'p.display_name as guest', 'r.adults', 'r.children', 'r.departure_date']);
        $arrivals = DB::table('pms_reservations')->where('property_id', $this->property->id)->where('arrival_date', $date)->whereIn('status', ['confirmed', 'tentative'])
            ->whereNotNull('room_id')->pluck('room_id')->flip();
        $outages = DB::table('pms_room_outages')->where('date_from', '<=', $date)->where('date_to', '>=', $date)->whereNull('released_at')->pluck('type', 'room_id');

        return $this->result([['floor', 'text'], ['room', 'text'], ['room_type', 'text'], ['fo_status', 'text'], ['hk_status', 'text'], ['outage', 'text'], ['guest', 'text'],
            ['persons', 'text'], ['movement', 'text']],
            $rows->map(fn ($r) => ['floor' => $r->floor, 'room' => $r->number, 'room_type' => $r->room_type, 'fo_status' => __('pms.history.fo.'.$r->fo_status),
                'hk_status' => __('pms.ooo.hk.'.$r->hk_status), 'outage' => isset($outages[$r->id]) ? strtoupper($outages[$r->id]) : '', 'guest' => $r->guest,
                'persons' => $r->guest ? $r->adults.($r->children ? '+'.$r->children : '') : '',
                'movement' => trim(($r->departure_date === $date ? __('pms.reports.due_out') : '').' '.(isset($arrivals[$r->id]) ? __('pms.reports.arrival_today') : ''))]),
            ['floor' => __('pms.reports.total'), 'room' => $rows->count(), 'guest' => $rows->whereNotNull('guest')->count()]);
    }

    private function outOfOrder(): array
    {
        $date = $this->f['from']->toDateString();
        $rows = DB::table('pms_room_outages as o')->join('pms_rooms as rm', 'rm.id', '=', 'o.room_id')->leftJoin('pms_reason_codes as rs', 'rs.id', '=', 'o.reason_code_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')->where('o.property_id', $this->property->id)->where('o.date_to', '>=', $date)->whereNull('o.released_at')
            ->orderBy('o.date_from')->orderBy('rm.number')->get(['rm.number', 'o.type', 'o.date_from', 'o.date_to', 'rs.name as reason', 'o.note', 'u.username']);

        return $this->result([['room', 'text'], ['type', 'text'], ['from', 'date'], ['to', 'date'], ['nights', 'int'], ['reason', 'text'], ['note', 'text'], ['user', 'text']],
            $rows->map(fn ($r) => ['room' => $r->number, 'type' => strtoupper($r->type), 'from' => $r->date_from, 'to' => $r->date_to,
                'nights' => CarbonImmutable::parse($r->date_from)->diffInDays(CarbonImmutable::parse($r->date_to)) + 1, 'reason' => $r->reason, 'note' => $r->note, 'user' => $r->username]),
            ['room' => __('pms.reports.total'), 'type' => $rows->count()]);
    }

    // ------------------------------------------------------------ Doluluk

    private function forecast(): array
    {
        [$from, $to] = [$this->f['from']->toDateString(), $this->f['to']->toDateString()];
        $rooms = (int) DB::table('pms_rooms')->where('property_id', $this->property->id)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at')->count();
        $nights = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->where('r.property_id', $this->property->id)->whereBetween('n.stay_date', [$from, $to])->where('n.status', '<>', 'cancelled')->where('r.is_day_use', false)
            ->whereIn('r.status', array_merge(self::SOLD, ['tentative']))->groupBy('n.stay_date')
            ->selectRaw("n.stay_date, SUM(CASE WHEN r.status = 'tentative' THEN 0 ELSE 1 END) AS sold, SUM(CASE WHEN r.status = 'tentative' THEN 1 ELSE 0 END) AS tentative,
                SUM(CASE WHEN r.status = 'tentative' THEN 0 ELSE n.adults + n.children END) AS persons, SUM(CASE WHEN r.status = 'tentative' THEN 0 ELSE n.amount END) AS revenue")
            ->get()->keyBy(fn ($r) => substr((string) $r->stay_date, 0, 10));
        $moves = fn (string $column) => DB::table('pms_reservations')->where('property_id', $this->property->id)->whereBetween($column, [$from, $to])
            ->whereIn('status', array_merge(self::SOLD, ['tentative']))->groupBy($column)->selectRaw("{$column} AS d, COUNT(*) AS c")->pluck('c', 'd')
            ->mapWithKeys(fn ($c, $d) => [substr((string) $d, 0, 10) => (int) $c]);
        $arrivals = $moves('arrival_date');
        $departures = $moves('departure_date');
        $outages = DB::table('pms_room_outages')->where('property_id', $this->property->id)->where('type', 'ooo')->where('date_from', '<=', $to)->where('date_to', '>=', $from)
            ->whereNull('released_at')->get(['date_from', 'date_to']);

        $rows = collect(CarbonPeriod::create($from, $to))->map(function ($day) use ($rooms, $nights, $arrivals, $departures, $outages) {
            $d = $day->toDateString();
            $n = $nights[$d] ?? null;
            $ooo = $outages->filter(fn ($o) => substr((string) $o->date_from, 0, 10) <= $d && substr((string) $o->date_to, 0, 10) >= $d)->count();
            $sold = (int) ($n->sold ?? 0);
            $sellable = max(0, $rooms - $ooo);

            return ['date' => $d, 'rooms' => $rooms, 'ooo' => $ooo, 'sold' => $sold, 'tentative' => (int) ($n->tentative ?? 0), 'free' => $sellable - $sold,
                'occupancy' => $sellable ? round($sold * 100 / $sellable, 1) : 0.0, 'arrivals' => $arrivals[$d] ?? 0, 'departures' => $departures[$d] ?? 0,
                'persons' => (int) ($n->persons ?? 0), 'revenue' => round((float) ($n->revenue ?? 0), 2), 'adr' => $sold ? round((float) $n->revenue / $sold, 2) : 0.0];
        });
        $sold = $rows->sum('sold');
        $sellable = $rows->sum(fn ($r) => $r['rooms'] - $r['ooo']);

        return $this->result([['date', 'date'], ['rooms', 'int'], ['ooo', 'int'], ['sold', 'int'], ['tentative', 'int'], ['free', 'int'], ['occupancy', 'pct'],
            ['arrivals', 'int'], ['departures', 'int'], ['persons', 'int'], ['revenue', 'money'], ['adr', 'money']], $rows,
            ['date' => __('pms.reports.total'), 'rooms' => $rows->sum('rooms'), 'ooo' => $rows->sum('ooo'), 'sold' => $sold, 'tentative' => $rows->sum('tentative'), 'free' => $rows->sum('free'),
                'occupancy' => $sellable ? round($sold * 100 / $sellable, 1) : 0.0, 'arrivals' => $rows->sum('arrivals'), 'departures' => $rows->sum('departures'),
                'persons' => $rows->sum('persons'), 'revenue' => round($rows->sum('revenue'), 2), 'adr' => $sold ? round($rows->sum('revenue') / $sold, 2) : 0.0]);
    }

    /** Yönetim raporu: gün, ay başından, yıl başından; geçen yılın aynı dönemiyle. Gelir folyo kayıtlarından (net), satılan oda gece satırlarından. */
    private function manager(): array
    {
        $d = $this->f['from'];
        $periods = ['day' => [$d, $d], 'mtd' => [$d->startOfMonth(), $d], 'ytd' => [$d->startOfYear(), $d]];
        $stats = [];
        foreach ($periods as $key => [$a, $b]) {
            $stats[$key] = $this->periodStats($a, $b);
            $stats[$key.'_ly'] = $this->periodStats($a->subYear(), $b->subYear());
        }
        $rows = collect(['rooms_available', 'rooms_sold', 'occupancy', 'persons', 'adr', 'revpar', 'room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue'])
            ->map(fn ($m) => ['metric' => __('pms.reports.metric.'.$m)] + collect($stats)->map(fn ($s) => $s[$m])->all() + ['type' => in_array($m, ['occupancy'], true) ? 'pct' : (in_array($m, ['rooms_available', 'rooms_sold', 'persons'], true) ? 'int' : 'money')]);

        return ['columns' => [['metric', 'text'], ['day', 'row'], ['day_ly', 'row'], ['mtd', 'row'], ['mtd_ly', 'row'], ['ytd', 'row'], ['ytd_ly', 'row']], 'rows' => $rows->all(), 'totals' => null];
    }

    /** @return array<string, float|int> */
    private function periodStats(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rooms = (int) DB::table('pms_rooms')->where('property_id', $this->property->id)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at')->count();
        $days = $from->diffInDays($to) + 1;
        $ooo = (int) DB::table('pms_room_outages')->where('property_id', $this->property->id)->where('type', 'ooo')->where('date_from', '<=', $to->toDateString())
            ->where('date_to', '>=', $from->toDateString())->get(['date_from', 'date_to'])
            ->sum(fn ($o) => max(0, CarbonImmutable::parse(max((string) $o->date_from, $from->toDateString()))->diffInDays(CarbonImmutable::parse(min(substr((string) $o->date_to, 0, 10), $to->toDateString()))) + 1));
        $sold = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')->where('r.property_id', $this->property->id)
            ->whereBetween('n.stay_date', [$from->toDateString(), $to->toDateString()])->where('n.status', '<>', 'cancelled')->where('r.is_day_use', false)
            ->whereIn('r.status', self::SOLD)->selectRaw('COUNT(*) AS c, SUM(n.adults + n.children) AS p')->first();
        $revenue = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')->where('p.property_id', $this->property->id)
            ->whereBetween('p.business_date', [$from->toDateString(), $to->toDateString()])->where('t.type', 'revenue')
            ->selectRaw("SUM(CASE WHEN t.revenue_bucket = 'room' THEN p.net_amount ELSE 0 END) AS room,
                SUM(CASE WHEN t.revenue_bucket IN ('food', 'beverage', 'breakfast', 'lunch', 'dinner', 'minibar') THEN p.net_amount ELSE 0 END) AS fnb,
                SUM(p.net_amount) AS total")->first();
        $available = max(0, $rooms * $days - $ooo);
        $soldCount = (int) ($sold->c ?? 0);
        $room = round((float) ($revenue->room ?? 0), 2);
        $total = round((float) ($revenue->total ?? 0), 2);
        $fnb = round((float) ($revenue->fnb ?? 0), 2);

        return ['rooms_available' => $available, 'rooms_sold' => $soldCount, 'occupancy' => $available ? round($soldCount * 100 / $available, 1) : 0.0,
            'persons' => (int) ($sold->p ?? 0), 'adr' => $soldCount ? round($room / $soldCount, 2) : 0.0, 'revpar' => $available ? round($room / $available, 2) : 0.0,
            'room_revenue' => $room, 'fnb_revenue' => $fnb, 'other_revenue' => round($total - $room - $fnb, 2), 'total_revenue' => $total];
    }

    // ------------------------------------------------------------ Finans

    private function departmentTotals(): array
    {
        $rows = $this->postings()->groupBy('t.code', 't.name', 't.type')->orderByRaw('CAST(t.code AS UNSIGNED)')
            ->selectRaw('t.code, t.name, t.type, COUNT(*) AS cnt, SUM(p.quantity) AS qty, SUM(p.net_amount) AS net, SUM(p.tax_amount) AS tax, SUM(p.amount) AS gross')->get();
        $revenue = $rows->where('type', 'revenue');

        return $this->result([['code', 'text'], ['name', 'text'], ['type', 'text'], ['count', 'int'], ['quantity', 'int'], ['net', 'money'], ['tax_amount', 'money'], ['gross', 'money']],
            $rows->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'type' => __('pms.dc.types.'.$r->type), 'count' => (int) $r->cnt, 'quantity' => (int) round((float) $r->qty),
                'net' => round((float) $r->net, 2), 'tax_amount' => round((float) $r->tax, 2), 'gross' => round((float) $r->gross, 2)]),
            ['code' => __('pms.reports.revenue_total'), 'count' => (int) $revenue->sum('cnt'), 'net' => round($revenue->sum('net'), 2), 'tax_amount' => round($revenue->sum('tax'), 2), 'gross' => round($revenue->sum('gross'), 2)]);
    }

    private function dailyRevenue(): array
    {
        $buckets = ['room', 'food', 'beverage', 'other'];
        $data = $this->postings()->where('t.type', 'revenue')->groupBy('p.business_date', 't.revenue_bucket')
            ->selectRaw('p.business_date, t.revenue_bucket, SUM(p.net_amount) AS net')->get()
            ->groupBy(fn ($r) => substr((string) $r->business_date, 0, 10));
        $bucket = fn (?string $b) => match ($b) {
            'room' => 'room', 'food', 'breakfast', 'lunch', 'dinner' => 'food', 'beverage', 'minibar' => 'beverage', default => 'other'
        };
        $rows = collect(CarbonPeriod::create($this->f['from'], $this->f['to']))->map(function ($day) use ($data, $bucket, $buckets) {
            $row = ['date' => $day->toDateString()] + array_fill_keys($buckets, 0.0);
            foreach ($data[$day->toDateString()] ?? [] as $r) {
                $row[$bucket($r->revenue_bucket)] = round($row[$bucket($r->revenue_bucket)] + (float) $r->net, 2);
            }

            return $row + ['total' => round(array_sum(array_intersect_key($row, array_flip($buckets))), 2)];
        });

        return $this->result(array_merge([['date', 'date']], array_map(fn ($b) => ['rev_'.$b, 'money'], $buckets), [['total', 'money']]),
            $rows->map(fn ($r) => ['date' => $r['date'], 'total' => $r['total']] + collect($buckets)->mapWithKeys(fn ($b) => ['rev_'.$b => $r[$b]])->all()),
            ['date' => __('pms.reports.total'), 'total' => round($rows->sum('total'), 2)] + collect($buckets)->mapWithKeys(fn ($b) => ['rev_'.$b => round($rows->sum($b), 2)])->all());
    }

    private function journal(): array
    {
        $date = $this->f['from']->toDateString();
        $rows = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')->join('pms_folios as f', 'f.id', '=', 'p.folio_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'p.room_id')->leftJoin('users as u', 'u.id', '=', 'p.posted_by')
            ->where('p.property_id', $this->property->id)->where('p.business_date', $date)->when($this->f['user'], fn ($q, $u) => $q->where('p.posted_by', $u))
            ->orderBy('p.id')->get(['p.created_at', 'rm.number as room', 'f.folio_number', 'f.name as account', 't.code', 'p.description', 'p.quantity', 'p.amount', 'p.source',
                'p.reversal_of_id', 'u.username', 'p.foreign_currency', 'p.foreign_amount']);

        return $this->result([['time', 'text'], ['room', 'text'], ['folio', 'text'], ['code', 'text'], ['description', 'text'], ['quantity', 'int'], ['amount', 'money'],
            ['foreign', 'text'], ['source', 'text'], ['user', 'text']],
            $rows->map(fn ($r) => ['time' => substr((string) $r->created_at, 11, 5), 'room' => $r->room ?? $r->account, 'folio' => $r->folio_number, 'code' => $r->code,
                'description' => ($r->reversal_of_id ? '↺ ' : '').$r->description, 'quantity' => (int) round((float) $r->quantity), 'amount' => round((float) $r->amount, 2),
                'foreign' => $r->foreign_currency ? number_format((float) $r->foreign_amount, 2, ',', '.').' '.$r->foreign_currency : '', 'source' => $r->source, 'user' => $r->username]),
            ['time' => __('pms.reports.total'), 'description' => $rows->count(), 'amount' => round($rows->sum('amount'), 2)]);
    }

    private function cashier(): array
    {
        $rows = $this->postings()->whereIn('t.type', ['payment', 'deposit', 'paid_out'])->leftJoin('users as u', 'u.id', '=', 'p.posted_by')
            ->when($this->f['user'], fn ($q, $u) => $q->where('p.posted_by', $u))
            ->groupBy('u.username', 't.code', 't.name', 'p.foreign_currency')->orderBy('u.username')->orderByRaw('CAST(t.code AS UNSIGNED)')
            ->selectRaw('u.username, t.code, t.name, p.foreign_currency, COUNT(*) AS cnt, SUM(-p.amount) AS base, SUM(-COALESCE(p.foreign_amount, p.amount)) AS fx')->get();

        return $this->result([['user', 'text'], ['code', 'text'], ['name', 'text'], ['currency', 'text'], ['count', 'int'], ['amount_fx', 'money'], ['amount_base', 'money']],
            $rows->map(fn ($r) => ['user' => $r->username ?? __('pms.audit_log.system'), 'code' => $r->code, 'name' => $r->name, 'currency' => $r->foreign_currency ?: $this->property->base_currency,
                'count' => (int) $r->cnt, 'amount_fx' => round((float) $r->fx, 2), 'amount_base' => round((float) $r->base, 2)]),
            ['user' => __('pms.reports.total'), 'count' => (int) $rows->sum('cnt'), 'amount_base' => round($rows->sum('base'), 2)]);
    }

    private function openBalances(): array
    {
        $rows = DB::table('pms_folios as f')->leftJoin('pms_reservations as r', 'r.id', '=', 'f.reservation_id')->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')
            ->leftJoin('pms_profiles as p', 'p.id', '=', 'f.payee_profile_id')->where('f.property_id', $this->property->id)->where('f.status', 'open')
            ->whereRaw('ABS(f.balance) >= 0.01')->orderBy('f.type')->orderBy('rm.number')->orderBy('f.folio_number')
            ->get(['f.folio_number', 'f.type', 'f.window', 'f.name', 'rm.number as room', 'p.display_name', 'r.status', 'r.departure_date', 'f.balance']);

        return $this->result([['folio', 'text'], ['type', 'text'], ['room', 'text'], ['guest', 'text'], ['status', 'text'], ['departure', 'date'], ['balance', 'money']],
            $rows->map(fn ($r) => ['folio' => $r->folio_number.($r->window ? ' / '.$r->window : ''), 'type' => __('pms.reports.folio_types.'.$r->type), 'room' => $r->room,
                'guest' => $r->name ?: $r->display_name, 'status' => $r->status ? __('pms.res.statuses.'.$r->status) : '', 'departure' => $r->departure_date, 'balance' => round((float) $r->balance, 2)]),
            ['folio' => __('pms.reports.total'), 'guest' => $rows->count(), 'balance' => round($rows->sum('balance'), 2)]);
    }

    private function tax(): array
    {
        $rows = DB::table('pms_posting_taxes as pt')->join('pms_postings as p', 'p.id', '=', 'pt.posting_id')->join('pms_taxes as x', 'x.id', '=', 'pt.tax_id')
            ->where('p.property_id', $this->property->id)->whereBetween('p.business_date', [$this->f['from']->toDateString(), $this->f['to']->toDateString()])
            ->groupBy('x.code', 'x.name', 'pt.rate')->orderBy('pt.rate')
            ->selectRaw('x.code, x.name, pt.rate, COUNT(*) AS cnt, SUM(pt.base_amount) AS base, SUM(pt.tax_amount) AS tax')->get();

        return $this->result([['code', 'text'], ['name', 'text'], ['rate', 'pct'], ['count', 'int'], ['base', 'money'], ['tax_amount', 'money'], ['gross', 'money']],
            $rows->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'rate' => round((float) $r->rate, 2), 'count' => (int) $r->cnt, 'base' => round((float) $r->base, 2),
                'tax_amount' => round((float) $r->tax, 2), 'gross' => round((float) $r->base + (float) $r->tax, 2)]),
            ['code' => __('pms.reports.total'), 'count' => (int) $rows->sum('cnt'), 'base' => round($rows->sum('base'), 2), 'tax_amount' => round($rows->sum('tax'), 2),
                'gross' => round($rows->sum('base') + $rows->sum('tax'), 2)]);
    }

    // ------------------------------------------------------------ Fiyat

    private function byRateCode(): array
    {
        $rows = $this->nights()->join('pms_rate_codes as rc', 'rc.id', '=', 'n.rate_code_id')->groupBy('rc.code', 'rc.name', 'n.currency_code')->orderBy('rc.code')
            ->selectRaw('rc.code, rc.name, n.currency_code, COUNT(DISTINCT n.reservation_id) AS res, COUNT(*) AS nights, SUM(n.adults + n.children) AS persons, SUM(n.amount) AS revenue')->get();

        return $this->result([['rate_code', 'text'], ['name', 'text'], ['currency', 'text'], ['reservations', 'int'], ['room_nights', 'int'], ['persons', 'int'], ['revenue', 'money'], ['adr', 'money']],
            $rows->map(fn ($r) => ['rate_code' => $r->code, 'name' => $r->name, 'currency' => $r->currency_code, 'reservations' => (int) $r->res, 'room_nights' => (int) $r->nights,
                'persons' => (int) $r->persons, 'revenue' => round((float) $r->revenue, 2), 'adr' => $r->nights ? round((float) $r->revenue / $r->nights, 2) : 0.0]),
            ['rate_code' => __('pms.reports.total'), 'room_nights' => (int) $rows->sum('nights'), 'persons' => (int) $rows->sum('persons')]);
    }

    private function rateDiscrepancy(): array
    {
        $rows = $this->nights()->where('n.is_overridden', true)->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->join('pms_rate_codes as rc', 'rc.id', '=', 'n.rate_code_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'n.room_id')->leftJoin('pms_reason_codes as rs', 'rs.id', '=', 'n.override_reason_code_id')
            ->orderBy('n.stay_date')->orderBy('rm.number')
            ->get(['n.stay_date', 'rm.number as room', 'p.display_name as guest', 'r.confirmation_number', 'rc.code as rate_code', 'n.amount', 'n.currency_code', 'rs.name as reason', 'r.status']);

        return $this->result([['date', 'date'], ['room', 'text'], ['guest', 'text'], ['confirmation', 'text'], ['rate_code', 'text'], ['rate', 'money'], ['currency', 'text'], ['reason', 'text'], ['status', 'text']],
            $rows->map(fn ($r) => ['date' => substr((string) $r->stay_date, 0, 10), 'room' => $r->room, 'guest' => $r->guest, 'confirmation' => $r->confirmation_number, 'rate_code' => $r->rate_code,
                'rate' => round((float) $r->amount, 2), 'currency' => $r->currency_code, 'reason' => $r->reason, 'status' => __('pms.res.statuses.'.$r->status)]),
            ['date' => __('pms.reports.total'), 'guest' => $rows->count()]);
    }

    // ------------------------------------------------------------ yardımcılar

    private function reservations(): Builder
    {
        return DB::table('pms_reservations as r')->join('pms_profiles as p', 'p.id', '=', 'r.profile_id')->join('pms_room_types as rt', 'rt.id', '=', 'r.room_type_id')
            ->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')->leftJoin('pms_rate_codes as rc', 'rc.id', '=', 'r.rate_code_id')
            ->leftJoin('pms_profiles as ag', 'ag.id', '=', 'r.agent_profile_id')->leftJoin('pms_profiles as co', 'co.id', '=', 'r.company_profile_id')
            ->where('r.property_id', $this->property->id)->when($this->f['room_type'], fn ($q, $t) => $q->where('r.room_type_id', $t));
    }

    private function rooms(): Builder
    {
        return DB::table('pms_rooms as rm')->join('pms_room_types as rt', 'rt.id', '=', 'rm.room_type_id')->leftJoin('pms_floors as fl', 'fl.id', '=', 'rm.floor_id')
            ->where('rm.property_id', $this->property->id)->where('rm.is_active', true)->where('rm.is_pseudo', false)->whereNull('rm.deleted_at')
            ->when($this->f['room_type'], fn ($q, $t) => $q->where('rm.room_type_id', $t))
            ->orderBy('fl.sort')->orderBy('rm.sort')->orderBy('rm.number');
    }

    private function postings(): Builder
    {
        return DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')->where('p.property_id', $this->property->id)
            ->whereBetween('p.business_date', [$this->f['from']->toDateString(), $this->f['to']->toDateString()]);
    }

    private function nights(): Builder
    {
        return DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')->where('r.property_id', $this->property->id)
            ->whereBetween('n.stay_date', [$this->f['from']->toDateString(), $this->f['to']->toDateString()])->where('n.status', '<>', 'cancelled')
            ->whereIn('r.status', array_merge(self::SOLD, ['tentative']));
    }

    /** @return Collection<int, float> rezervasyon id => açık folyoların bakiye toplamı */
    private function balances(Collection $ids): Collection
    {
        return DB::table('pms_folios')->whereIn('reservation_id', $ids->all() ?: [0])->groupBy('reservation_id')->selectRaw('reservation_id, SUM(balance) AS b')
            ->pluck('b', 'reservation_id')->map(fn ($b) => round((float) $b, 2));
    }

    private function result(array $columns, iterable $rows, ?array $totals): array
    {
        return ['columns' => $columns, 'rows' => collect($rows)->values()->all(), 'totals' => $totals];
    }
}
