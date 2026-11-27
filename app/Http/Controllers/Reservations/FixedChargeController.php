<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\FixedChargeService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rezervasyon kartı → Fixed Charges sekmesi (Ctrl+8): sabit ücret ekle / sil. */
class FixedChargeController extends Controller
{
    public function __construct(private readonly FixedChargeService $charges) {}

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $p = app(Property::class);
        abort_unless((int) $reservation->property_id === (int) $p->id, 404);
        $bd = $p->current_business_date->toImmutable();
        foreach (['date_from', 'date_to'] as $field) {
            $raw = trim((string) $request->input($field));
            $request->merge([$field => $raw === '' ? null : (BusinessDateInput::parse($raw, $bd)?->toDateString() ?? 'x')]);
        }
        $request->merge(['amount' => str_replace(',', '.', (string) $request->input('amount')), 'quantity' => str_replace(',', '.', (string) ($request->input('quantity') ?: '1'))]);
        $data = $request->validate([
            'transaction_code_id' => ['required', 'integer', Rule::exists('pms_transaction_codes', 'id')->where('property_id', $p->id)->whereNotIn('type', ['payment', 'deposit'])->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'frequency' => ['required', Rule::in(FixedChargeService::FREQUENCIES)],
            'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'folio_window' => ['nullable', 'integer', 'between:1,8'],
        ], [], [
            'transaction_code_id' => __('pms.fixed.code'), 'amount' => __('pms.fixed.amount'), 'date_from' => __('pms.fixed.from'), 'date_to' => __('pms.fixed.to'),
        ]);
        try {
            $this->charges->add($reservation, $data + ['folio_window' => (int) ($data['folio_window'] ?? 1)]);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage())->with('tab', 'fixed_charges');
        }

        return back()->with('status', __('pms.fixed.added'))->with('tab', 'fixed_charges');
    }

    public function destroy(Reservation $reservation, int $charge): RedirectResponse
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
        $this->charges->delete($reservation, $charge);

        return back()->with('status', __('pms.fixed.deleted'))->with('tab', 'fixed_charges');
    }
}
