<?php

namespace App\Providers;

use App\Domain\Core\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Yetki: kullanıcının o anki tesisteki rolünde izin var mı (pms_user_properties → pms_role_permissions).
        // Kullanım: middleware('permission:setup.outlets'), @can('permission', 'setup.outlets')
        Gate::define('permission', function (User $user, string $code): bool {
            $propertyId = app()->bound(Property::class) ? app(Property::class)->id : null;

            return DB::table('pms_user_properties as up')
                ->join('pms_role_permissions as rp', 'rp.role_id', '=', 'up.role_id')
                ->join('pms_permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('up.user_id', $user->id)->when($propertyId, fn ($q) => $q->where('up.property_id', $propertyId))
                ->where('p.code', $code)->exists();
        });
    }
}
