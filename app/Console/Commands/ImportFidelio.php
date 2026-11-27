<?php

namespace App\Console\Commands;

use App\Domain\Migration\FidelioImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Fidelio Suite8 veri aktarımı (PROISV view CSV'leri). Ayrıntı: docs/06-fidelio-gecis.md §6.4.
 * Önce --dry-run ile denenir; doğrulama raporu storage/app/private/imports/ altına yazılır (kişisel veri içermez).
 */
class ImportFidelio extends Command
{
    protected $signature = 'pms:import-fidelio
        {dir : PROISV CSV dosyalarının olduğu klasör}
        {--property=RCK}
        {--only= : profiles,reservations,guests,balances (virgülle; boşsa hepsi)}
        {--from= : Kesim tarihi; bu tarihten önce ayrılan rezervasyonlar aktarılmaz (varsayılan: iş günü)}
        {--with-cancelled : İleri tarihli iptal rezervasyonları da aktar}
        {--balance-code=879 : Açık bakiye devri için departman kodu}
        {--ledgers=0 : Bakiyeye katılan POSTING_LEDGERSTATUS değerleri (virgülle)}
        {--dry-run : Yaz, raporla, sonra geri al}';

    protected $description = 'Fidelio profillerini, ileri tarihli / içerideki rezervasyonları, ek misafirleri ve açık bakiyeleri aktarır';

    public function handle(FidelioImporter $importer): int
    {
        $dir = (string) $this->argument('dir');
        if (! is_dir($dir)) {
            $this->error("Klasör yok: {$dir}");

            return self::FAILURE;
        }
        $propertyId = DB::table('pms_properties')->where('code', $this->option('property'))->value('id');
        if (! $propertyId) {
            $this->error('Tesis bulunamadı.');

            return self::FAILURE;
        }
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_diff($only, array_keys(FidelioImporter::FILES));
        if ($unknown) {
            $this->error('Bilinmeyen bölüm: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $report = $importer->run((int) $propertyId, $dir, array_filter([
            'dry_run' => (bool) $this->option('dry-run'),
            'only' => $only ?: null,
            'from' => $this->option('from') ?: null,
            'with_cancelled' => (bool) $this->option('with-cancelled'),
            'balance_code' => (string) $this->option('balance-code'),
            'ledgers' => array_map('trim', explode(',', (string) $this->option('ledgers'))),
        ], fn ($v) => $v !== null));

        $text = self::format($report);
        $this->line($text);
        $path = storage_path('app/private/imports/fidelio-'.now()->format('Ymd-His').($report['dry_run'] ? '-deneme' : '').'.txt');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $text);
        $this->info("Rapor: {$path}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    public static function format(array $report): string
    {
        $lines = ['Fidelio aktarımı — '.$report['property'].($report['dry_run'] ? ' (DENEME: hiçbir şey kaydedilmedi)' : ''),
            'Klasör: '.$report['directory'], 'Kesim tarihi: '.$report['cutoff'], 'Başlangıç: '.$report['started_at'].'  Bitiş: '.($report['finished_at'] ?? ''), ''];
        foreach ($report['entities'] as $entity => $stats) {
            $lines[] = '['.$entity.']';
            foreach ($stats as $key => $value) {
                $lines[] = '  '.str_pad($key, 28).(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value);
            }
            $lines[] = '';
        }
        if ($report['missing_codes']) {
            $lines[] = '[RS PMS\'te bulunmayan kodlar] (kod tablolarına ekleyin ya da eslesme.json ile eşleyin)';
            foreach ($report['missing_codes'] as $kind => $codes) {
                arsort($codes);
                $lines[] = '  '.str_pad($kind, 18).collect($codes)->map(fn ($n, $c) => "{$c} ({$n})")->implode(', ');
            }
            $lines[] = '';
        }
        if (! empty($report['errors'])) {
            $lines[] = '[Hatalar] '.count($report['errors']).' kayıt (ilk 50; tamamı pms_legacy_import_errors)';
            foreach (array_slice($report['errors'], 0, 50) as $error) {
                $lines[] = '  '.$error;
            }
        }

        return implode("\n", $lines);
    }
}
