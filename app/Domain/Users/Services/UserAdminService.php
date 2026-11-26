<?php

namespace App\Domain\Users\Services;

use App\Console\Commands\ImportFidelioUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ayarlar → Kullanıcılar ve Roller (Fidelio Setup → Users / User Groups).
 * Kullanıcı silinmez, pasif yapılır (kayıtlarda kim yaptı bilgisi kalır). Her kullanıcının tesiste tek rolü vardır.
 * Kendi hesabını pasif yapamaz, kendi rolünü yetkisiz bir role çeviremez (kendini kilitlememek için).
 */
class UserAdminService
{
    public const MANAGE_PERMISSION = 'setup.users';

    /**
     * @param  array{username: string, name: string, email: ?string, initials: ?string, title: ?string, locale: string, is_active: bool, role_id: int}  $data
     * @return array{0: User, 1: ?string} kullanıcı ve (yeni kullanıcıysa) geçici şifre
     */
    public function save(int $propertyId, array $data, ?User $user, int $actorId): array
    {
        $roleId = (int) $data['role_id'];
        unset($data['role_id']);
        $data['username'] = Str::upper(trim($data['username']));
        $data['email'] = ($data['email'] ?? null) ?: ($user?->email ?? Str::lower(Str::ascii($data['username'])).'@rocks.local');

        if ($user && $user->id === $actorId) {
            if (! $data['is_active']) {
                throw new UserAdminException(__('pms.users.err_self_inactive'));
            }
            if (! $this->roleHas($roleId, self::MANAGE_PERMISSION)) {
                throw new UserAdminException(__('pms.users.err_self_role'));
            }
        }

        return DB::transaction(function () use ($propertyId, $data, $user, $roleId) {
            $password = null;
            if (! $user) {
                $password = ImportFidelioUsers::password();
                $user = User::create($data + ['password' => Hash::make($password), 'must_change_password' => true]);
            } else {
                $user->update($data);
            }
            DB::table('pms_user_properties')->where('user_id', $user->id)->where('property_id', $propertyId)->delete();
            DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $propertyId, 'role_id' => $roleId,
                'created_at' => now(), 'updated_at' => now()]);

            return [$user, $password];
        });
    }

    /** Yeni geçici şifre; kullanıcı ilk girişte değiştirmek zorunda. */
    public function resetPassword(User $user): string
    {
        $password = ImportFidelioUsers::password();
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();

        return $password;
    }

    /** @param array<int> $permissionIds */
    public function saveRole(array $data, array $permissionIds, ?int $roleId, int $actorId, int $propertyId): int
    {
        $data['code'] = Str::upper(trim($data['code']));
        $manage = (int) DB::table('pms_permissions')->where('code', self::MANAGE_PERMISSION)->value('id');
        if ($roleId && $roleId === $this->actorRole($actorId, $propertyId) && ! in_array($manage, $permissionIds, true)) {
            throw new UserAdminException(__('pms.roles.err_self_permission'));
        }

        return DB::transaction(function () use ($data, $permissionIds, $roleId) {
            if ($roleId) {
                DB::table('pms_roles')->where('id', $roleId)->update($data + ['updated_at' => now()]);
            } else {
                $roleId = DB::table('pms_roles')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('pms_role_permissions')->where('role_id', $roleId)->delete();
            $valid = DB::table('pms_permissions')->whereIn('id', $permissionIds)->pluck('id');
            DB::table('pms_role_permissions')->insert($valid->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all());

            return $roleId;
        });
    }

    public function deleteRole(int $roleId): void
    {
        if (DB::table('pms_roles')->where('id', $roleId)->value('code') === 'admin') {
            throw new UserAdminException(__('pms.roles.err_admin'));
        }
        if (DB::table('pms_user_properties')->where('role_id', $roleId)->exists()) {
            throw new UserAdminException(__('pms.roles.err_in_use'));
        }
        DB::table('pms_roles')->where('id', $roleId)->delete();
    }

    private function roleHas(int $roleId, string $permission): bool
    {
        return DB::table('pms_role_permissions as rp')->join('pms_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)->where('p.code', $permission)->exists();
    }

    private function actorRole(int $actorId, int $propertyId): ?int
    {
        $id = DB::table('pms_user_properties')->where('user_id', $actorId)->where('property_id', $propertyId)->value('role_id');

        return $id ? (int) $id : null;
    }
}
