<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationExtrasService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Rezervasyon kartı → Inventory (Ctrl+2): sayılı kalem ekle / sil; stok tarih aralığında denetlenir. */
class ReservationItemController extends Controller
{
    public function store(Request $request, Reservation $reservation, ReservationExtrasService $extras): RedirectResponse
    {
        $p = app(Property::class);
        abort_unless((int) $reservation->property_id === (int) $p->id, 404);
        $bd = $p->current_business_date->toImmutable();
        foreach (['date_from', 'date_to'] as $f) {
            $request->merge([$f => BusinessDateInput::parse((string) $request->input($f), $bd)?->toDateString() ?? 'x']);
        }
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('pms_items', 'id')->where('property_id', $p->id)],
            'quantity' => ['required', 'integer', 'between:1,20'], 'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after:date_from'],
        ]);
        if ($data['date_from'] < $reservation->arrival_date->toDateString() || $data['date_to'] > $reservation->departure_date->toDateString()) {
            return back()->with('error', __('pms.items.outside_stay'));
        }
        $free = $extras->itemAvailable((int) $data['item_id'], $data['date_from'], $data['date_to']);
        if ($free < (int) $data['quantity']) {
            return back()->with('error', __('pms.items.not_enough', ['free' => $free]));
        }
        DB::table('pms_reservation_items')->insert($data + ['reservation_id' => $reservation->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('pms.items.added'));
    }

    public function destroy(Reservation $reservation, int $item): RedirectResponse
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
        DB::table('pms_reservation_items')->where('reservation_id', $reservation->id)->where('id', $item)->delete();

        return back()->with('status', __('pms.items.deleted'));
    }
}
