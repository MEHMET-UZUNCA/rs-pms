<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reservation → Overbooking (Fidelio Overbooking): oda tipi ve gün bazında izin verilen fazla satış. Rezervasyon kartı bu sınıra kadar uyarısız
 * kaydeder; sınır aşılınca yalnız reservations.overbooking yetkisi olan "yine de kaydet" ile kaydedebilir. Web sitesi yalnız gerçek boş odayı satar.
 */
class OverbookingController extends Controller
{
    public const SPANS = [14, 31];

    public function index(Request $request, AvailabilityService $availability): View
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $start = BusinessDateInput::parse((string) $request->query('bas', ''), $bd) ?? $bd;
        $span = in_array((int) $request->query('gun'), self::SPANS, true) ? (int) $request->query('gun') : 14;
        $end = $start->addDays($span - 1);
        $availability->rebuild($p->id, $start, $end);

        $days = [];
        for ($i = 0; $i < $span; $i++) {
            $days[] = $start->addDays($i);
        }

        return view('reservations.overbooking', [
            'types' => DB::table('pms_room_types')->where('property_id', $p->id)->where('is_pseudo', false)->whereNull('deleted_at')->orderBy('sort')->get(['id', 'code', 'name']),
            'cells' => DB::table('pms_inventory_daily')->where('property_id', $p->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
                ->groupBy('room_type_id')->map(fn ($d) => $d->keyBy(fn ($x) => substr((string) $x->date, 0, 10))),
            'days' => $days, 'start' => $start, 'span' => $span, 'bd' => $bd,
        ]);
    }

    /** Hücre hücre (limit[tip][tarih]) ya da aralık (tipler, başlangıç, bitiş, sınır) kaydı. 0 = overbooking yok. */
    public function save(Request $request, AvailabilityService $availability): RedirectResponse
    {
        $p = app(Property::class);
        $bd = $p->current_business_date->toImmutable();
        $types = DB::table('pms_room_types')->where('property_id', $p->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $rows = [];
        if ($request->has('limit')) {
            $request->validate(['limit' => ['array'], 'limit.*' => ['array'], 'limit.*.*' => ['nullable', 'integer', 'between:0,99']]);
            foreach ((array) $request->input('limit') as $typeId => $dates) {
                foreach ((array) $dates as $date => $value) {
                    if (in_array((int) $typeId, $types, true) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                        $rows[] = [(int) $typeId, $date, (int) $value];
                    }
                }
            }
        } else {
            foreach (['from', 'to'] as $f) {
                $request->merge([$f => BusinessDateInput::parse((string) $request->input($f), $bd)?->toDateString() ?? 'x']);
            }
            $data = $request->validate(['room_types' => ['required', 'array', 'min:1'], 'room_types.*' => ['integer', Rule::in($types)],
                'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'value' => ['required', 'integer', 'between:0,99']],
                [], ['room_types' => __('pms.res.room_type'), 'value' => __('pms.ob.limit')]);
            if ((strtotime($data['to']) - strtotime($data['from'])) / 86400 > 366) {
                return back()->with('error', __('pms.ob.too_long'));
            }
            foreach ($data['room_types'] as $typeId) {
                for ($d = $data['from']; $d <= $data['to']; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
                    $rows[] = [(int) $typeId, $d, (int) $data['value']];
                }
            }
        }
        if (! $rows) {
            return back();
        }
        DB::transaction(function () use ($rows, $p, $request) {
            foreach ($rows as [$typeId, $date, $value]) {
                $key = ['property_id' => $p->id, 'room_type_id' => $typeId, 'date' => $date];
                $value > 0
                    ? DB::table('pms_overbooking_limits')->updateOrInsert($key, ['limit' => $value, 'created_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()])
                    : DB::table('pms_overbooking_limits')->where($key)->delete();
            }
        });
        $dates = array_column($rows, 1);
        $availability->rebuild($p->id, CarbonImmutable::parse(min($dates)), CarbonImmutable::parse(max($dates)));

        return back()->with('status', __('pms.ob.saved', ['count' => count($rows)]));
    }
}
