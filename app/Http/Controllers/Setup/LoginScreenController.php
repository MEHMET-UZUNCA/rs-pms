<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\LoginAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Ayarlar → Giriş ekranı: arka plan görseli (yükle / kaldır), karartma, karşılama başlığı ve metni. */
class LoginScreenController extends Controller
{
    public function edit(): View
    {
        return view('setup.login-screen', ['a' => LoginAppearance::get(app(Property::class)->id)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $p = app(Property::class)->id;
        $data = $request->validate([
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=500'],
            'overlay' => ['required', 'integer', 'between:0,85'],
            'title' => ['nullable', 'string', 'max:80'], 'subtitle' => ['nullable', 'string', 'max:200'],
        ], [], ['image' => __('pms.loginscreen.image'), 'overlay' => __('pms.loginscreen.overlay')]);
        $values = ['overlay' => (int) $data['overlay'], 'title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null];
        $old = LoginAppearance::get($p);
        if ($request->hasFile('image')) {
            $ext = strtolower($request->file('image')->extension() ?: 'jpg');
            $path = $request->file('image')->storeAs(LoginAppearance::DIR, 'login-background.'.$ext, LoginAppearance::DISK);
            if ($old['image'] && $old['image'] !== $path) {
                Storage::disk(LoginAppearance::DISK)->delete($old['image']);
            }
            $values += ['image' => $path, 'image_version' => (string) time()];
        } elseif ($request->boolean('remove_image') && $old['image']) {
            Storage::disk(LoginAppearance::DISK)->delete($old['image']);
            $values += ['image' => null, 'image_version' => null];
        }
        LoginAppearance::save($p, $values, $request->user()->id);

        return back()->with('status', __('pms.loginscreen.saved'));
    }

    /** Giriş ekranının arka planı (oturum gerektirmez; tarayıcı bir gün önbellekte tutar, sürüm parametresi değişince yenilenir). */
    public function image(): Response
    {
        $a = LoginAppearance::get();
        abort_unless($a['image'], 404);

        return response()->file(Storage::disk(LoginAppearance::DISK)->path($a['image']), ['Cache-Control' => 'public, max-age=86400']);
    }
}
