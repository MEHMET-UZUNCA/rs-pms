<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** İlk kurulumda kullanıcı açar ve tesise rolüyle bağlar: php artisan pms:create-user MEHMET "Mehmet U." mail@ornek.com --role=admin */
class CreateUser extends Command
{
    protected $signature = 'pms:create-user {username} {name} {email} {--role=admin} {--property=RCK} {--password= : Verilmezse sorulur}';

    protected $description = 'Kullanıcı açar ve tesise rolüyle bağlar';

    public function handle(): int
    {
        $propertyId = DB::table('pms_properties')->where('code', $this->option('property'))->value('id');
        $roleId = DB::table('pms_roles')->where('code', $this->option('role'))->value('id');
        if (! $propertyId || ! $roleId) {
            $this->error('Tesis ya da rol bulunamadı (önce: php artisan db:seed).');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Şifre');
        if (strlen((string) $password) < 8) {
            $this->error('Şifre en az 8 karakter olmalı.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => Str::lower($this->argument('email'))], [
            'username' => Str::upper($this->argument('username')), 'name' => $this->argument('name'),
            'password' => Hash::make($password), 'is_active' => true,
        ]);
        DB::table('pms_user_properties')->updateOrInsert(
            ['user_id' => $user->id, 'property_id' => $propertyId, 'role_id' => $roleId],
            ['updated_at' => now(), 'created_at' => now()],
        );
        $this->info("{$user->username} açıldı ({$this->option('role')}, {$this->option('property')}).");

        return self::SUCCESS;
    }
}
