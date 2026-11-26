<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fidelio kullanıcılarını açar: rastgele geçici şifre, rol (Fidelio User Group), ilk girişte şifre değiştirme zorunlu.
 * Şifreler ve yetkiler tek bir metin dosyasına yazılır (depo dışında, storage/app/private). Var olan kullanıcıya dokunulmaz (--reset hariç).
 * Girdi: sekmeyle ayrılmış Role, Initials, Name, Title, Login (Role = Fidelio grup kodu: SUP, FOM, FOA …).
 *   php artisan pms:import-users storage/app/private/fidelio-users.tsv
 */
class ImportFidelioUsers extends Command
{
    protected $signature = 'pms:import-users {file} {--property=RCK} {--output= : Şifre dosyası (varsayılan storage/app/private/kullanici-sifreleri-<tarih>.txt)} {--reset : Var olan kullanıcıların şifresini de yenile}';

    protected $description = 'Fidelio kullanıcılarını rastgele şifre ve rolüyle açar, şifreleri yetkileriyle birlikte dosyaya yazar';

    public function handle(): int
    {
        $propertyId = DB::table('pms_properties')->where('code', $this->option('property'))->value('id');
        if (! $propertyId || ! is_readable($this->argument('file'))) {
            $this->error('Tesis ya da dosya bulunamadı.');

            return self::FAILURE;
        }

        $created = [];
        $skipped = [];
        foreach (file($this->argument('file'), FILE_IGNORE_NEW_LINES) as $line) {
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'Role')) {
                continue;
            }
            [$roleCode, $initials, $name, $title, $login] = array_map('trim', array_pad(explode("\t", $line), 5, ''));
            $role = DB::table('pms_roles')->where('code', $roleCode)->first();
            $username = Str::upper($login);
            if (! $role || $username === '') {
                $skipped[] = "{$login}: rol bulunamadı ({$roleCode})";

                continue;
            }
            $existing = User::where('username', $username)->first();
            if ($existing && ! $this->option('reset')) {
                $skipped[] = "{$username}: zaten var, şifresine dokunulmadı";

                continue;
            }

            $password = self::password();
            $user = User::updateOrCreate(['username' => $username], [
                'name' => Str::title(Str::lower($name)), 'initials' => $initials, 'title' => $title,
                'email' => $existing?->email ?? Str::lower(Str::ascii($login)).'@rocks.local',
                'password' => Hash::make($password), 'must_change_password' => true, 'is_active' => true, 'locale' => 'en',
            ]);
            DB::table('pms_user_properties')->where('user_id', $user->id)->where('property_id', $propertyId)->delete();
            DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $propertyId, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
            $permissions = DB::table('pms_role_permissions as rp')->join('pms_permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('rp.role_id', $role->id)->orderBy('p.group')->orderBy('p.code')->get(['p.code', 'p.name']);
            $created[] = [$user, $password, $role, $permissions];
        }

        $file = $this->option('output') ?: storage_path('app/private/kullanici-sifreleri-'.now()->format('Y-m-d-His').'.txt');
        file_put_contents($file, self::report($created, $skipped));
        @chmod($file, 0600);

        $this->info(count($created).' kullanıcı açıldı, '.count($skipped).' atlandı.');
        $this->line("Şifre dosyası: {$file}");
        $this->warn('Bu dosyayı paylaştıktan sonra silin. Kullanıcılar ilk girişte şifrelerini değiştirmek zorunda.');

        return self::SUCCESS;
    }

    /** 12 karakter; karışan harfler (0/O, 1/l/I) yok; en az bir büyük harf, küçük harf ve rakam. */
    public static function password(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
        $chars = array_map(fn ($set) => $set[random_int(0, strlen($set) - 1)], $sets);
        $all = implode('', $sets);
        while (count($chars) < 12) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {   // karıştır
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function report(array $created, array $skipped): string
    {
        $out = ['RS PMS — Rocks Hotel kullanıcıları ve geçici şifreleri ('.now()->format('d.m.Y H:i').')',
            'GİZLİDİR. Her kullanıcı ilk girişte kendi şifresini belirler; bu şifreler o andan sonra geçersizdir.', str_repeat('=', 78), ''];
        foreach ($created as [$user, $password, $role, $permissions]) {
            $out[] = sprintf('%-12s %-26s Şifre: %s', $user->username, $user->name, $password);
            $out[] = sprintf('             Rol: %s — %s', $role->code, $role->name);
            $out[] = '             Yetkiler: '.($permissions->isEmpty() ? '(yok)' : $permissions->map(fn ($p) => $p->name.' ('.$p->code.')')->implode(', '));
            $out[] = '';
        }
        if ($skipped) {
            $out[] = 'Atlananlar:';
            foreach ($skipped as $s) {
                $out[] = '  - '.$s;
            }
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }
}
