<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\MealPlanService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Multiboard: rezervasyonda gece bazında pansiyon değişikliği (Meal Plan sekmesi) ve mutfak için gün gün öğün sayıları. */
class MealPlanController extends Controller
{
    public function __construct(private readonly MealPlanService $meals) {}

    public function forecast(Request $request): View
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $from = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? $bd;
        $days = max(1, min(31, (int) $request->query('gun', 7)));

        return view('reservations.meal-forecast', [
            'rows' => $this->meals->forecast($p->id, $from, $from->addDays($days - 1)), 'from' => $from, 'days' => $days,
            'boards' => DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
                ->leftJoin('pms_packages as pk', 'pk.id', '=', 'n.package_id')
                ->where('n.property_id', $p->id)->where('n.status', '!=', 'cancelled')->whereIn('r.status', ['confirmed', 'tentative', 'in_house', 'checked_out'])
                ->whereBetween('n.stay_date', [$from->toDateString(), $from->addDays($days - 1)->toDateString()])
                ->groupBy('pk.code')->selectRaw("COALESCE(pk.code, '-') AS board, COUNT(*) AS room_nights, SUM(n.adults) AS adults, SUM(n.children) AS children")
                ->orderBy('board')->get(),
        ]);
    }

    public function change(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'package_id' => ['nullable', 'integer']]);
        try {
            $count = $this->meals->changeBoard($reservation, $data['from'], $data['to'], $data['package_id'] ? (int) $data['package_id'] : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.meal.changed', ['count' => $count]));
    }
}
