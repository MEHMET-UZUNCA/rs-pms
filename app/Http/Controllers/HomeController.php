<?php

namespace App\Http\Controllers;

use App\Domain\Core\Models\Property;
use App\Domain\FrontOffice\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Ana sayfa: ön büro kontrol paneli (rezervasyon ya da kat hizmetleri yetkisi olana); diğerlerine kullanabildiği ekranlar. */
class HomeController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        $show = Gate::allows('permission', 'reservations.view') || Gate::allows('permission', 'housekeeping.view');

        return view('home', ['dash' => $show ? $dashboard->compute(app(Property::class)->id) : null]);
    }
}
