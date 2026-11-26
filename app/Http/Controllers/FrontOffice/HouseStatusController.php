<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Domain\Housekeeping\Services\HouseStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Rooms Management → House Status (Fidelio Ctrl+H). Rezervasyon ya da kat hizmetleri yetkisi olan görür; 5 dakikada bir yenilenir. */
class HouseStatusController extends Controller
{
    public function __invoke(Request $request, HouseStatusService $status): View
    {
        abort_unless(Gate::allows('permission', 'reservations.view') || Gate::allows('permission', 'housekeeping.view'), 403);

        return view('front-office.house-status', ['s' => $status->compute(app(Property::class)->id, $request->boolean('cocuk'), $request->boolean('gunubirlik'))]);
    }
}
