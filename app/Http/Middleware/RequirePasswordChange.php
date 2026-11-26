<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rastgele şifreyle açılan kullanıcı, şifresini değiştirmeden başka ekrana geçemez. */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.*', 'logout', 'locale')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
