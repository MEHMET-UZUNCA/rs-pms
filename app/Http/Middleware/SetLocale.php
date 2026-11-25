<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Arayüz dili: kullanıcının seçtiği dil, yoksa oturumdaki seçim, yoksa varsayılan (İngilizce). Desteklenen diller: config('app.locales'). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.locales', ['en' => 'English']));
        $locale = $request->user()?->locale ?? $request->session()->get('locale');
        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
