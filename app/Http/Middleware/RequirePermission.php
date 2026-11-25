<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Kullanım: ->middleware('permission:setup.outlets') — kullanıcının o anki tesisteki rolünde izin yoksa 403. */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        abort_unless(Gate::allows('permission', $code), 403, 'You are not allowed to use this screen.');

        return $next($request);
    }
}
