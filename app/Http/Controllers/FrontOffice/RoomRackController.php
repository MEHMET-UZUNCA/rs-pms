<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Oda Planı (Fidelio "Room Rack", Ctrl+L): odalar × günler. Odası atanmış rezervasyonlar çubuk olarak (In House / Expected / Tentative),
 * arızalı (OOO) ve hizmet dışı (OOS) dönemler gri; satırda oda tipi ve kat hizmeti durumu; altta günlük doluluk ve odası atanmamış rezervasyonlar.
 */
class RoomRackController extends Controller
{
    public const SPANS = [7, 14, 30];

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $bd = CarbonImmutable::parse($property->current_business_date);
        $start = BusinessDateInput::parse($request->query('bas'), $bd) ?? $bd;
        $span = in_array((int) $request->query('gun'), self::SPANS, true) ? (int) $request->query('gun') : 14;
        $end = $start->addDays($span);   // hariç
        $typeId = (int) $request->query('tip');
        $floorId = (int) $request->query('kat');

        $rooms = DB::table('pms_rooms as r')->join('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->leftJoin('pms_floors as f', 'f.id', '=', 'r.floor_id')
            ->where('r.property_id', $property->id)->where('r.is_active', true)->where('r.is_pseudo', false)
            ->when($typeId, fn ($q) => $q->where('r.room_type_id', $typeId))
            ->when($floorId, fn ($q) => $q->where('r.floor_id', $floorId))
            ->orderBy('r.number')
            ->get(['r.id', 'r.number', 'r.fo_status', 'r.hk_status', 'r.service_status', 't.code as type_code', 'f.code as floor_code']);

        $day = fn ($date) => (int) $start->diffInDays(CarbonImmutable::parse($date), false);
        $bar = function ($from, $to) use ($day, $span) {
            $a = max(0, $day($from));
            $b = min($span, $day($to));

            return $b > $a ? ['col' => $a, 'len' => $b - $a, 'cont_left' => $day($from) < 0, 'cont_right' => $day($to) > $span] : null;
        };

        $bars = [];
        DB::table('pms_reservations as res')->join('pms_profiles as p', 'p.id', '=', 'res.profile_id')
            ->where('res.property_id', $property->id)->whereIn('res.room_id', $rooms->pluck('id'))
            ->whereIn('res.status', ['confirmed', 'tentative', 'waitlist', 'in_house'])
            ->where('res.arrival_date', '<', $end->toDateString())->where('res.departure_date', '>', $start->toDateString())
            ->get(['res.id', 'res.room_id', 'res.status', 'res.arrival_date', 'res.departure_date', 'res.adults', 'res.children', 'p.display_name'])
            ->each(function ($r) use (&$bars, $bar) {
                if ($b = $bar($r->arrival_date, $r->departure_date)) {
                    $bars[$r->room_id][] = $b + ['kind' => $r->status === 'in_house' ? 'in_house' : ($r->status === 'confirmed' ? 'confirmed' : 'tentative'),
                        'label' => $r->display_name, 'id' => $r->id, 'title' => $r->display_name.' · '.CarbonImmutable::parse($r->arrival_date)->format('d.m').
                        '–'.CarbonImmutable::parse($r->departure_date)->format('d.m').' · '.$r->adults.($r->children ? '+'.$r->children : '')];
                }
            });
        DB::table('pms_room_outages')->whereIn('room_id', $rooms->pluck('id'))->whereNull('released_at')
            ->where('date_from', '<', $end->toDateString())->where('date_to', '>', $start->toDateString())
            ->get(['room_id', 'type', 'date_from', 'date_to', 'note'])
            ->each(function ($o) use (&$bars, $bar) {
                if ($b = $bar($o->date_from, $o->date_to)) {
                    $bars[$o->room_id][] = $b + ['kind' => strtolower($o->type) === 'oos' ? 'oos' : 'ooo', 'label' => strtoupper($o->type).($o->note ? ' · '.$o->note : ''),
                        'id' => null, 'title' => strtoupper($o->type).' '.$o->note];
                }
            });

        // Günlük özet: dolu oda (atanmış rezervasyon) ve odası atanmamış rezervasyon sayısı
        $days = [];
        $active = DB::table('pms_reservations')->where('property_id', $property->id)->whereIn('status', ['confirmed', 'tentative', 'waitlist', 'in_house'])
            ->where('arrival_date', '<', $end->toDateString())->where('departure_date', '>', $start->toDateString())
            ->get(['room_id', 'arrival_date', 'departure_date']);
        $total = DB::table('pms_rooms')->where('property_id', $property->id)->where('is_active', true)->where('is_pseudo', false)->count();
        for ($i = 0; $i < $span; $i++) {
            $d = $start->addDays($i)->toDateString();
            $on = $active->filter(fn ($r) => $r->arrival_date <= $d && $r->departure_date > $d);
            $occupied = $on->whereNotNull('room_id')->count();
            $days[] = ['date' => $start->addDays($i), 'occupied' => $occupied, 'unassigned' => $on->whereNull('room_id')->count(),
                'pct' => $total ? round($occupied * 100 / $total) : 0];
        }

        return view('front-office.room-rack', [
            'rooms' => $rooms, 'bars' => $bars, 'days' => $days, 'start' => $start, 'span' => $span, 'businessDate' => $bd,
            'typeId' => $typeId, 'floorId' => $floorId,
            'types' => DB::table('pms_room_types')->where('property_id', $property->id)->orderBy('sort')->get(['id', 'code']),
            'floors' => DB::table('pms_floors')->where('property_id', $property->id)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
