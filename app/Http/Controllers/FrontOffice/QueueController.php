<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\QueueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Front Desk → Queue Rooms (Fidelio): odası hazır olmayan, beklemedeki misafirler. */
class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queue) {}

    public function index(): View
    {
        return view('front-office.queue', ['rows' => $this->queue->list(app(Property::class)->id)]);
    }

    public function add(Reservation $reservation): RedirectResponse
    {
        $this->own($reservation);
        try {
            $this->queue->add($reservation);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $reservation->id);
        }

        return back()->with('status', __('pms.queue.added', ['name' => $reservation->profile?->display_name]))->with('selected', $reservation->id);
    }

    public function remove(Reservation $reservation): RedirectResponse
    {
        $this->own($reservation);
        $this->queue->remove($reservation);

        return back()->with('status', __('pms.queue.removed', ['name' => $reservation->profile?->display_name]));
    }

    private function own(Reservation $reservation): void
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
    }
}
