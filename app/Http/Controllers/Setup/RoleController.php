<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Domain\Users\Services\UserAdminException;
use App\Domain\Users\Services\UserAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ayarlar → Sistem → Roller (Fidelio User Groups / User Rights). Rol başına yetki listesi, modüllere göre gruplu.
 */
class RoleController extends Controller
{
    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $roles = DB::table('pms_roles as r')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('r.code', 'like', "%{$search}%")->orWhere('r.name', 'like', "%{$search}%")))
            ->orderBy('r.code')
            ->get(['r.id', 'r.code', 'r.name'])
            ->map(function ($r) {
                $r->permissions = DB::table('pms_role_permissions')->where('role_id', $r->id)->count();
                $r->users = DB::table('pms_user_properties as up')->join('users as u', 'u.id', '=', 'up.user_id')
                    ->where('up.role_id', $r->id)->where('up.property_id', app(Property::class)->id)->where('u.is_active', true)->count();

                return $r;
            });

        return view('setup.roles.index', [
            'roles' => $roles, 'search' => $search, 'total' => DB::table('pms_permissions')->count(),
            'selected' => (int) $request->query('secili', session('selected', 0)),
        ]);
    }

    public function create(): View
    {
        return $this->form((object) ['id' => null, 'code' => '', 'name' => ''], []);
    }

    public function edit(int $role): View
    {
        $row = DB::table('pms_roles')->find($role) ?? abort(404);

        return $this->form($row, DB::table('pms_role_permissions')->where('role_id', $role)->pluck('permission_id')->map(fn ($id) => (int) $id)->all());
    }

    /** Fidelio "Copy": aynı yetkilerle yeni rol. */
    public function copy(int $role): View
    {
        $row = DB::table('pms_roles')->find($role) ?? abort(404);
        $copy = (object) ['id' => null, 'code' => '', 'name' => $row->name.' '.__('pms.outlet.copy_suffix')];

        return $this->form($copy, DB::table('pms_role_permissions')->where('role_id', $role)->pluck('permission_id')->map(fn ($id) => (int) $id)->all());
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        DB::table('pms_roles')->where('id', $role)->exists() || abort(404);

        return $this->save($request, $role);
    }

    public function destroy(int $role): RedirectResponse
    {
        $row = DB::table('pms_roles')->find($role) ?? abort(404);
        try {
            $this->users->deleteRole($role);
        } catch (UserAdminException $e) {
            return back()->with('error', $e->getMessage())->with('selected', $role);
        }

        return redirect()->route('setup.roles.index')->with('status', __('pms.common.deleted', ['name' => $row->code]));
    }

    private function save(Request $request, ?int $role): RedirectResponse
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('pms_roles', 'code')->ignore($role)],
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'], 'permissions.*' => ['integer'],
        ], ['code.regex' => __('pms.validation.outlet_code_regex')], ['code' => __('pms.common.code'), 'name' => __('pms.common.name')]);
        $permissions = array_map('intval', $data['permissions'] ?? []);
        unset($data['permissions']);

        try {
            $id = $this->users->saveRole($data, $permissions, $role, $request->user()->id, app(Property::class)->id);
        } catch (UserAdminException $e) {
            return back()->withInput()->withErrors(['permissions' => $e->getMessage()]);
        }

        return redirect()->route('setup.roles.index')->with('status', __('pms.common.saved', ['name' => $data['code']]))->with('selected', $id);
    }

    /** @param array<int> $assigned */
    private function form(object $role, array $assigned): View
    {
        return view('setup.roles.form', [
            'role' => $role, 'assigned' => $assigned,
            'permissions' => DB::table('pms_permissions')->orderBy('group')->orderBy('id')->get(['id', 'code', 'name', 'group'])->groupBy('group'),
        ]);
    }
}
