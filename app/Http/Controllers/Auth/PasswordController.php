<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Şifre değiştirme (ilk girişte zorunlu). Yeni şifre en az 8 karakter, harf ve rakam içerir, eskisiyle aynı olamaz. */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => __('pms.password.current'), 'password' => __('pms.password.new')]);

        $request->user()->update(['password' => Hash::make($request->input('password')), 'must_change_password' => false, 'password_changed_at' => now()]);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', __('pms.password.changed'));
    }
}
