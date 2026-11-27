<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Veritabanı yedeği: MySQL / MariaDB'de mysqldump (--single-transaction, tutarlı ve kilitsiz), SQLite'ta dosya kopyası; gzip'lenir,
 * BACKUP_PATH'e (varsayılan storage/app/backups) yazılır, BACKUP_COPY_PATH verilmişse (ör. ağ paylaşımı / ikinci disk) oraya da kopyalanır,
 * BACKUP_KEEP_DAYS'ten eski yedekler silinir. Parola komut satırına değil geçici seçenek dosyasına yazılır.
 */
class BackupDatabase extends Command
{
    protected $signature = 'pms:backup {--connection= : Veritabanı bağlantısı (boşsa varsayılan)} {--keep= : Kaç günlük yedek kalsın}';

    protected $description = 'Veritabanı yedeği al (gzip), ikinci yere kopyala, eskileri sil';

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');
        $db = config("database.connections.{$name}");
        $dir = rtrim((string) (env('BACKUP_PATH') ?: storage_path('app/backups')), '/');
        File::ensureDirectoryExists($dir, 0750);
        $stamp = now()->format('Y-m-d_His');
        $file = "{$dir}/rs-pms_{$name}_{$stamp}.".($db['driver'] === 'sqlite' ? 'sqlite.gz' : 'sql.gz');

        if ($db['driver'] === 'sqlite') {
            if (! is_file($db['database'])) {
                $this->error('SQLite dosyası yok: '.$db['database']);

                return self::FAILURE;
            }
            file_put_contents($file, gzencode((string) file_get_contents($db['database']), 6));
        } elseif (in_array($db['driver'], ['mysql', 'mariadb'], true)) {
            $cnf = tempnam(sys_get_temp_dir(), 'rspms');
            file_put_contents($cnf, "[client]\nuser=\"{$db['username']}\"\npassword=\"{$db['password']}\"\nhost=\"{$db['host']}\"\nport=\"{$db['port']}\"\n");
            chmod($cnf, 0600);
            $dump = (new ExecutableFinder)->find('mysqldump') ?? (new ExecutableFinder)->find('mariadb-dump');
            $process = Process::fromShellCommandline('"$DUMP" --defaults-extra-file="$CNF" --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 "$DB" | gzip -6 > "$OUT"');
            $process->setTimeout(3600)->run(null, ['DUMP' => $dump, 'CNF' => $cnf, 'DB' => $db['database'], 'OUT' => $file]);
            @unlink($cnf);
            if (! $process->isSuccessful() || filesize($file) < 100) {
                @unlink($file);
                $this->error('Yedek alınamadı: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        } else {
            $this->error("Desteklenmeyen sürücü: {$db['driver']}");

            return self::FAILURE;
        }
        chmod($file, 0640);
        $this->info('Yedek: '.$file.' ('.number_format(filesize($file) / 1024, 0, ',', '.').' KB)');

        if ($copy = env('BACKUP_COPY_PATH')) {
            File::ensureDirectoryExists($copy);
            File::copy($file, rtrim($copy, '/').'/'.basename($file)) ? $this->info('Kopya: '.$copy) : $this->warn('Kopyalanamadı: '.$copy);
        }

        $keep = (int) ($this->option('keep') ?: env('BACKUP_KEEP_DAYS', 30));
        $deleted = 0;
        foreach (glob("{$dir}/rs-pms_*") ?: [] as $old) {
            if (filemtime($old) < now()->subDays($keep)->getTimestamp() && @unlink($old)) {
                $deleted++;
            }
        }
        $deleted && $this->line("{$deleted} eski yedek silindi ({$keep} günden eski).");

        return self::SUCCESS;
    }
}
