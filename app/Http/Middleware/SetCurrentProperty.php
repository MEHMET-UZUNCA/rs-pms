<?php

namespace App\Http\Middleware;

use App\Domain\Core\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oturumdaki tesisi belirler (kullanıcının yetkili olduğu tesislerden). Tesis yoksa erişim yok.
 * Ekranlar `app(Property::class)` ile tesisi alır; görünümlere $currentProperty olarak verilir.
 */
class SetCurrentProperty
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = DB::table('pms_user_properties as up')->join('pms_properties as p', 'p.id', '=', 'up.property_id')
            ->where('up.user_id', $request->user()->id)->where('p.is_active', true)->orderBy('p.id')->distinct()->pluck('p.id')->all();
        abort_if($allowed === [], 403, 'Bu kullanıcı için tanımlı tesis yok.');

        $id = (int) $request->session()->get('property_id');
        if (! in_array($id, array_map('intval', $allowed), true)) {
            $id = (int) $allowed[0];
            $request->session()->put('property_id', $id);
        }

        $property = Property::findOrFail($id);
        app()->instance(Property::class, $property);
        View::share('currentProperty', $property);

        return $next($request);
    }
}
