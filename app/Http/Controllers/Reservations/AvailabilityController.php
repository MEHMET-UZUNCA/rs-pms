<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Müsaitlik (Fidelio Availability / Elektraweb oda tipi müsaitliği): oda tipi × gün satılabilir oda; altta toplam satılan, opsiyon,
 * blokta kalan, arızalı ve doluluk. Hücreye tıklayınca o tip ve tarihle yeni rezervasyon açılır.
 */
class AvailabilityController extends Controller
{
    public const SPANS = [7, 14, 21, 31];

    public function index(Request $request, AvailabilityService $availability): View
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $start = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? $bd;
        $span = in_array((int) $request->query('gun'), self::SPANS, true) ? (int) $request->query('gun') : 14;
        $end = $start->addDays($span - 1);
        $availability->rebuild($p->id, $start, $end);

        $types = DB::table('pms_room_types')->where('property_id', $p->id)->where('is_pseudo', false)->whereNull('deleted_at')->orderBy('sort')->get(['id', 'code', 'name']);
        $rows = DB::table('pms_inventory_daily')->where('property_id', $p->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->groupBy('room_type_id')->map(fn ($days) => $days->keyBy(fn ($d) => substr((string) $d->date, 0, 10)));
        $days = [];
        for ($i = 0; $i < $span; $i++) {
            $date = $start->addDays($i);
            $key = $date->toDateString();
            $all = $rows->map(fn ($d) => $d[$key] ?? null)->filter();
            $sellable = $all->sum(fn ($d) => $d->physical_rooms - $d->out_of_order);
            $days[] = ['date' => $date, 'key' => $key, 'available' => $all->sum('available'), 'sold' => $all->sum('sold_definite'), 'tentative' => $all->sum('sold_tentative'),
                'blocks' => $all->sum('block_remaining_definite') + $all->sum('block_remaining_tentative'), 'ooo' => $all->sum('out_of_order'), 'oos' => $all->sum('out_of_service'),
                'pct' => $sellable > 0 ? round($all->sum('sold_definite') / $sellable * 100, 1) : 0.0];
        }

        return view('reservations.availability', ['types' => $types, 'rows' => $rows, 'days' => $days, 'start' => $start, 'span' => $span, 'bd' => $bd]);
    }
}
