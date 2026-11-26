<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Users\Services\UserAdminException;
use App\Domain\Users\Services\UserAdminService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Sistem → Kullanıcılar (Fidelio Setup → Users). Liste, yeni / düzenle, geçici şifre, pasif yapma.
 * Yeni kullanıcı ve şifre sıfırlamada geçici şifre bir kez ekranda gösterilir; kullanıcı ilk girişte değiştirir.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request): View
    {
        $property = app(Property::class);
        $search = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('pasif');

        $users = DB::table('users as u')
            ->join('pms_user_properties as up', fn ($j) => $j->on('up.user_id', '=', 'u.id')->where('up.property_id', $property->id))
            ->join('pms_roles as r', 'r.id', '=', 'up.role_id')
            ->when(! $showInactive, fn ($q) => $q->where('u.is_active', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('u.username', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%")
                ->orWhere('r.code', 'like', "%{$search}%")->orWhere('r.name', 'like', "%{$search}%")))
            ->orderBy('u.username')
            ->get(['u.id', 'u.username', 'u.name', 'u.initials', 'u.title', 'u.locale', 'u.is_active', 'u.last_login_at', 'u.must_change_password', 'r.code as role_code', 'r.name as role_name']);

        return view('setup.users.index', [
            'users' => $users, 'search' => $search, 'showInactive' => $showInactive,
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true, 'locale' => 'en']), null);
    }

    public function edit(User $user): View
    {
        return $this->form($user, $this->roleOf($user));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->roleOf($user) ?? abort(404);

        return $this->save($request, $user);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->roleOf($user) ?? abort(404);
        $password = $this->users->resetPassword($user);

        return redirect()->route('setup.users.index')->with('selected', $user->id)
            ->with('temp_password', ['username' => $user->username, 'password' => $password]);
    }

    private function save(Request $request, ?User $user): RedirectResponse
    {
        $property = app(Property::class);
        $request->merge(['username' => mb_strtoupper(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9._\-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'initials' => ['nullable', 'string', 'max:5'],
            'title' => ['nullable', 'string', 'max:60'],
            'locale' => ['required', Rule::in(array_keys(config('app.locales')))],
            'role_id' => ['required', 'integer', Rule::exists('pms_roles', 'id')],
            'is_active' => ['boolean'],
        ], ['username.regex' => __('pms.users.err_username')], [
            'username' => __('pms.users.username'), 'name' => __('pms.common.name'), 'email' => __('pms.users.email'),
            'initials' => __('pms.users.initials'), 'title' => __('pms.users.title_field'), 'locale' => __('pms.language'), 'role_id' => __('pms.users.role'),
        ]);
        $data['is_active'] = $request->boolean('is_active');

        try {
            [$saved, $password] = $this->users->save($property->id, $data, $user, $request->user()->id);
        } catch (UserAdminException $e) {
            return back()->withInput()->withErrors(['is_active' => $e->getMessage()]);
        }

        $redirect = redirect()->route('setup.users.index', ['pasif' => $saved->is_active ? null : 1])
            ->with('status', __('pms.common.saved', ['name' => $saved->username]))->with('selected', $saved->id);

        return $password ? $redirect->with('temp_password', ['username' => $saved->username, 'password' => $password]) : $redirect;
    }

    private function form(User $user, ?int $roleId): View
    {
        return view('setup.users.form', [
            'user' => $user, 'roleId' => $roleId,
            'roles' => DB::table('pms_roles')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /** Kullanıcının bu tesisteki rolü; tesise bağlı değilse null. */
    private function roleOf(User $user): ?int
    {
        $id = DB::table('pms_user_properties')->where('user_id', $user->id)->where('property_id', app(Property::class)->id)->value('role_id');
        abort_if($id === null && $user->exists, 404);

        return $id ? (int) $id : null;
    }
}
