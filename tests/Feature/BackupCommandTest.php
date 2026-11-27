<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    public function test_backup_writes_gzip_copies_and_prunes(): void
    {
        $base = sys_get_temp_dir().'/rspms-backup-test-'.uniqid();
        mkdir($base);
        $db = "{$base}/db.sqlite";
        $pdo = new \PDO("sqlite:{$db}");
        $pdo->exec('CREATE TABLE t (x TEXT); INSERT INTO t VALUES ("rocks")');
        config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $db, 'prefix' => '']]);
        putenv("BACKUP_PATH={$base}/out");
        putenv("BACKUP_COPY_PATH={$base}/copy");
        mkdir("{$base}/out");
        touch("{$base}/out/rs-pms_old.sql.gz", strtotime('-40 days'));

        $this->assertSame(0, Artisan::call('pms:backup', ['--connection' => 'backup_test', '--keep' => 30]));
        $files = glob("{$base}/out/rs-pms_backup_test_*.sqlite.gz");
        $this->assertCount(1, $files);
        $this->assertStringContainsString('rocks', gzdecode(file_get_contents($files[0])));
        $this->assertCount(1, glob("{$base}/copy/rs-pms_backup_test_*.sqlite.gz"));
        $this->assertFileDoesNotExist("{$base}/out/rs-pms_old.sql.gz");   // 30 günden eski silindi

        putenv('BACKUP_PATH');
        putenv('BACKUP_COPY_PATH');
        array_map('unlink', array_merge(glob("{$base}/*/*"), [$db]));
    }
}
