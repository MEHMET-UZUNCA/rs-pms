<?php

namespace App\Http\Controllers\Housekeeping;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Rooms Management → Out of Order (arızalı / hizmet dışı oda listesi: güncel, planlanan, geçmiş) ve Room History (bir odanın
 * misafirleri, durum değişiklikleri, arızaları, oda değişiklikleri). Arıza girişi ve kaldırma kat hizmetleri servisindeki kurallarla.
 */
class RoomsManagementController extends Controller
{
    public const TABS = ['current', 'planned', 'past'];

    public function outOfOrder(Request $request): View
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toDateString();
        $tab = in_array($request->query('sekme'), self::TABS, true) ? $request->query('sekme') : 'current';
        $type = in_array($request->query('tur'), ['ooo', 'oos'], true) ? $request->query('tur') : null;
        $room = trim((string) $request->query('oda', ''));

        $base = fn () => DB::table('pms_room_outages as o')->join('pms_rooms as r', 'r.id', '=', 'o.room_id')
            ->where('o.property_id', $p->id)
            ->when($type, fn ($q) => $q->where('o.type', $type))
            ->when($room !== '', fn ($q) => $q->where('r.number', $room));
        $scopes = [
            'current' => fn ($q) => $q->whereNull('o.released_at')->where('o.date_from', '<=', $bd)->where('o.date_to', '>', $bd),
            'planned' => fn ($q) => $q->whereNull('o.released_at')->where('o.date_from', '>', $bd),
            'past' => fn ($q) => $q->where(fn ($w) => $w->whereNotNull('o.released_at')->orWhere('o.date_to', '<=', $bd)),
        ];
        $outages = $scopes[$tab]($base())
            ->leftJoin('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->leftJoin('pms_reason_codes as rc', 'rc.id', '=', 'o.reason_code_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'o.created_by')
            ->leftJoin('users as ru', 'ru.id', '=', 'o.released_by')
            ->orderBy($tab === 'past' ? 'o.date_to' : 'o.date_from', $tab === 'past' ? 'desc' : 'asc')->orderBy('r.number')->limit(500)
            ->get(['o.id', 'o.type', 'o.date_from', 'o.date_to', 'o.note', 'o.released_at', 'o.return_hk_status', 'r.number', 't.code as room_type',
                'rc.name as reason', 'cu.username as created_by', 'ru.username as released_by', 'o.created_at']);

        return view('housekeeping.out-of-order', [
            'outages' => $outages, 'tab' => $tab, 'type' => $type, 'room' => $room, 'businessDate' => $p->current_business_date,
            'counts' => collect(self::TABS)->mapWithKeys(fn ($t) => [$t => $scopes[$t]($base())->count()]),
            'reasons' => DB::table('pms_reason_codes')->where('property_id', $p->id)->where('category', 'room_status')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'rooms' => DB::table('pms_rooms')->where('property_id', $p->id)->where('is_active', true)->where('is_pseudo', false)->whereNull('deleted_at')->orderBy('number')->get(['id', 'number', 'fo_status']),
            'selected' => (int) $request->query('secili', 0),
        ]);
    }

    public function history(Request $request): View
    {
        abort_unless(Gate::allows('permission', 'housekeeping.view') || Gate::allows('permission', 'reservations.view'), 403);
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $from = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? $bd->subMonths(6);
        $to = BusinessDateInput::parse((string) $request->query('bit', ''), $bd) ?? $bd->addMonths(3);
        $number = trim((string) $request->query('oda', ''));
        $room = $number !== '' ? DB::table('pms_rooms as r')->leftJoin('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->where('r.property_id', $p->id)->where('r.number', $number)->first(['r.*', 't.code as type_code', 't.name as type_name']) : null;

        $data = ['room' => $room, 'number' => $number, 'from' => $from, 'to' => $to, 'guests' => collect(), 'statuses' => collect(), 'outages' => collect(), 'moves' => collect()];
        if ($room) {
            $data['guests'] = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
                ->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')->leftJoin('pms_rate_codes as rc', 'rc.id', '=', 'r.rate_code_id')
                ->where('n.room_id', $room->id)->where('n.status', '!=', 'cancelled')->whereBetween('n.stay_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('r.id', 'r.confirmation_number', 'g.display_name', 'r.arrival_date', 'r.departure_date', 'r.status', 'rc.code', 'r.adults', 'r.children')
                ->orderByDesc('r.arrival_date')
                ->selectRaw('r.id, r.confirmation_number, g.display_name, r.arrival_date, r.departure_date, r.status, rc.code AS rate_code, r.adults, r.children, COUNT(*) AS nights_here, SUM(n.amount) AS revenue')
                ->get();
            $data['statuses'] = DB::table('pms_room_status_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->where('l.room_id', $room->id)
                ->whereBetween('l.created_at', [$from->startOfDay(), $to->endOfDay()])->orderByDesc('l.id')->limit(300)
                ->get(['l.field', 'l.from_value', 'l.to_value', 'l.source', 'l.created_at', 'u.username']);
            $data['outages'] = DB::table('pms_room_outages as o')->leftJoin('pms_reason_codes as rc', 'rc.id', '=', 'o.reason_code_id')
                ->where('o.room_id', $room->id)->where('o.date_to', '>=', $from->toDateString())->where('o.date_from', '<=', $to->toDateString())
                ->orderByDesc('o.date_from')->get(['o.type', 'o.date_from', 'o.date_to', 'o.released_at', 'o.note', 'rc.name as reason']);
            $data['moves'] = DB::table('pms_reservation_room_moves as m')->join('pms_reservations as r', 'r.id', '=', 'm.reservation_id')
                ->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')->leftJoin('pms_rooms as fr', 'fr.id', '=', 'm.from_room_id')->join('pms_rooms as tr', 'tr.id', '=', 'm.to_room_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                ->where(fn ($q) => $q->where('m.from_room_id', $room->id)->orWhere('m.to_room_id', $room->id))
                ->whereBetween('m.business_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('m.id')
                ->get(['m.business_date', 'fr.number as from_room', 'tr.number as to_room', 'g.display_name', 'u.username']);
        }

        return view('housekeeping.room-history', $data);
    }
}
