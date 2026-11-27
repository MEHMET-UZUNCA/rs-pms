<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\DepositService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rezervasyon kartı → Credit Card sekmesi → Depozito istekleri: ekle, elle ödendi / iptal. */
class DepositController extends Controller
{
    public function store(Request $request, Reservation $reservation, DepositService $deposits): RedirectResponse
    {
        $p = app(Property::class);
        abort_unless((int) $reservation->property_id === (int) $p->id, 404);
        $request->merge(['due_date' => BusinessDateInput::parse((string) $request->input('due_date'), $p->current_business_date->toImmutable())?->toDateString() ?? 'x',
            'amount' => str_replace(',', '.', (string) $request->input('amount'))]);
        $data = $request->validate(['due_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:pms_currencies,code']], [], ['amount' => __('pms.fixed.amount'), 'due_date' => __('pms.deposit.due')]);
        try {
            $deposits->request($reservation, $data['due_date'], (float) $data['amount'], strtoupper($data['currency_code']));
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('pms.deposit.added'));
    }

    public function status(Request $request, Reservation $reservation, int $deposit, DepositService $deposits): RedirectResponse
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['paid', 'cancelled', 'pending'])]]);
        $deposits->setStatus($reservation, $deposit, $data['status']);

        return back()->with('status', __('pms.deposit.status_saved', ['status' => __('pms.deposit.statuses.'.$data['status'])]));
    }
}
