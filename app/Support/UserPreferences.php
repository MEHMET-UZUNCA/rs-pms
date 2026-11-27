<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Kullanıcı tercihleri (users.preferences JSON): ekran bazında kolon düzeni, panel yüksekliği … */
class UserPreferences
{
    public static function get(?User $user, string $key, mixed $default = null): mixed
    {
        if (! $user) {
            return $default;
        }
        $all = json_decode((string) DB::table('users')->where('id', $user->id)->value('preferences'), true) ?: [];

        return Arr::get($all, $key, $default);
    }

    public static function set(User $user, string $key, mixed $value): void
    {
        $all = json_decode((string) DB::table('users')->where('id', $user->id)->value('preferences'), true) ?: [];
        $value === null ? Arr::forget($all, $key) : Arr::set($all, $key, $value);
        DB::table('users')->where('id', $user->id)->update(['preferences' => json_encode($all, JSON_UNESCAPED_UNICODE)]);
    }
}
