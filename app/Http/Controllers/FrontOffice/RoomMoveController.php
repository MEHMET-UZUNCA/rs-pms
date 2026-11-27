<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Front Desk → Room Moves (Elektraweb Oda Değişim Listesi): tarih aralığında oda değişimleri, nedeni (sonradan da girilebilir), kullanıcı. */
class RoomMoveController extends Controller
{
    public function index(Request $request): View
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $from = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? $bd;
        $to = BusinessDateInput::parse((string) $request->query('bit', ''), $bd) ?? $from;
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return view('front-office.room-moves', [
            'rows' => DB::table('pms_reservation_room_moves as m')->join('pms_reservations as r', 'r.id', '=', 'm.reservation_id')
                ->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')->leftJoin('pms_rooms as a', 'a.id', '=', 'm.from_room_id')
                ->leftJoin('pms_room_types as at', 'at.id', '=', 'a.room_type_id')->join('pms_rooms as b', 'b.id', '=', 'm.to_room_id')
                ->leftJoin('pms_room_types as bt', 'bt.id', '=', 'b.room_type_id')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                ->where('r.property_id', $p->id)->whereBetween('m.business_date', [$from->toDateString(), $to->toDateString()])
                ->orderByDesc('m.id')->limit(500)
                ->get(['m.id', 'm.business_date', 'm.created_at', 'm.reason_code_id', 'r.id as reservation_id', 'r.confirmation_number', 'r.status', 'g.display_name',
                    'a.number as from_room', 'at.code as from_type', 'b.number as to_room', 'bt.code as to_type', 'u.username']),
            'reasons' => DB::table('pms_reason_codes')->where('property_id', $p->id)->where('category', 'room_move')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function reason(Request $request, int $move): RedirectResponse
    {
        $p = app(Property::class);
        $row = DB::table('pms_reservation_room_moves as m')->join('pms_reservations as r', 'r.id', '=', 'm.reservation_id')->where('m.id', $move)->where('r.property_id', $p->id)->first(['m.id']);
        abort_unless($row, 404);
        $data = $request->validate(['reason_code_id' => ['nullable', 'integer', Rule::exists('pms_reason_codes', 'id')->where('property_id', $p->id)->where('category', 'room_move')]]);
        DB::table('pms_reservation_room_moves')->where('id', $row->id)->update(['reason_code_id' => $data['reason_code_id'] ?? null]);

        return back()->with('status', __('pms.moves.saved'));
    }
}
