<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Support\ReservationOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ayarlar → Rezervasyon seçenekleri (Fidelio Global Settings → Reservation 1). */
class ReservationOptionsController extends Controller
{
    public function edit(): View
    {
        $p = app(Property::class)->id;
        $codes = fn (string $table) => \Illuminate\Support\Facades\DB::table($table)->where('property_id', $p)->whereNull('deleted_at')->orderBy('code')->get(['code', 'name']);

        return view('setup.options.reservation', ['options' => ReservationOptions::get($p),
            'resTypes' => $codes('pms_reservation_types'), 'markets' => $codes('pms_market_segments'), 'sources' => $codes('pms_source_codes')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $time = ['required', 'date_format:H:i'];
        $status = ['required', Rule::in(ReservationOptions::ROOM_STATUSES)];
        $data = $request->validate([
            'check_in_time' => $time, 'check_out_time' => $time, 'day_use_check_in_time' => $time, 'day_use_check_out_time' => $time,
            'status_after_check_in' => $status, 'status_after_check_out' => $status, 'status_after_audit_occupied' => $status, 'status_after_no_show' => $status,
            'dirty_room_check_in' => ['required', Rule::in(ReservationOptions::DIRTY_CHECK_IN)],
            'web_reservation_type' => ['nullable', 'string', 'max:10'], 'web_market' => ['nullable', 'string', 'max:10'], 'web_source' => ['nullable', 'string', 'max:10'],
            'web_status' => ['nullable', Rule::in(['confirmed', 'tentative'])],
        ]);
        $data['web_status'] ??= 'confirmed';
        foreach (['queue_rooms', 'meal_plan'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        foreach (['meals_first_day', 'meals_departure_day', 'meals_day_use'] as $group) {
            $data[$group] = ['breakfast' => $request->boolean("{$group}.breakfast"), 'lunch' => $request->boolean("{$group}.lunch"), 'dinner' => $request->boolean("{$group}.dinner")];
        }
        ReservationOptions::save(app(Property::class)->id, $data, $request->user()->id);

        return redirect()->route('setup.options.reservation')->with('status', __('pms.options.saved'));
    }
}
