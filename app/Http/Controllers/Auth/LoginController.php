<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Fidelio giriş penceresi: kullanıcı adı (ya da e-posta) ve şifre. */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => __('pms.login.throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $value = $field === 'username' ? Str::upper($data['login']) : Str::lower($data['login']);

        if (! Auth::attempt([$field => $value, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => __('pms.login.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        User::whereKey(Auth::id())->update(['last_login_at' => now()]);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
