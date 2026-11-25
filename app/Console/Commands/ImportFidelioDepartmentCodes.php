<?php

namespace App\Console\Commands;

use App\Domain\Rates\Services\FidelioDepartmentCodeImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Fidelio "Department Codes" listesini içe aktarır (No; Code; Description; Manual; Group). */
class ImportFidelioDepartmentCodes extends Command
{
    protected $signature = 'pms:import-fidelio-department-codes {file : Fidelio\'dan dışa aktarılan liste (CSV / sekmeli metin)} {--property=RCK}';

    protected $description = 'Fidelio departman kodlarını (kategori, grup, vergi departmanları dahil) işlem kodlarına aktarır';

    public function handle(FidelioDepartmentCodeImporter $importer): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Dosya okunamadı: {$file}");

            return self::FAILURE;
        }
        $propertyId = DB::table('pms_properties')->where('code', $this->option('property'))->value('id');
        if (! $propertyId) {
            $this->error('Tesis bulunamadı.');

            return self::FAILURE;
        }
        $content = file_get_contents($file);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1254');
        }
        $rows = FidelioDepartmentCodeImporter::parse($content);
        $result = $importer->import((int) $propertyId, $rows);
        $this->info(count($rows)." satır okundu: {$result['created']} yeni, {$result['updated']} güncellenen kod; {$result['tax_departments']} vergi departmanı; {$result['discount_links']} indirim/rebate bağlantısı.");

        return self::SUCCESS;
    }
}
