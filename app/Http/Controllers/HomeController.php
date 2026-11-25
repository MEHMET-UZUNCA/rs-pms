<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Başlangıç ekranı (ileride Kamra'daki "Bugün" ekranı). */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home');
    }
}
