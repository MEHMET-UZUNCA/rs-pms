<?php

namespace App\Console\Commands;

use App\Domain\Core\Services\TaxRateService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fidelio'dan çıkarılan KDV oranlarını içe aktarır.
 * Dosya: Fidelio'da çalıştırılan oran sorgusunun CSV çıktısı (docs/06-fidelio-gecis.md); ilk iki kolon: departman kodu ; oran (%).
 */
class ImportFidelioTaxRates extends Command
{
    protected $signature = 'pms:import-fidelio-tax-rates {file : CSV dosyası (DC_NUMBER;KDV_ORANI)} {--property=RCK} {--from= : Geçerlilik başlangıcı (YYYY-AA-GG), boşsa bugün}';

    protected $description = 'Fidelio kayıtlarından hesaplanan KDV oranlarını departman kodlarına bağlar';

    public function handle(TaxRateService $service): int
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

        $rates = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cols = str_getcsv(str_replace(["\t", ','], ';', $line), ';');
            if (count($cols) < 2 || ! is_numeric(trim($cols[0]))) {
                continue; // başlık satırı
            }
            $rate = trim($cols[count($cols) >= 3 && ! is_numeric(str_replace(',', '.', trim($cols[1]))) ? 2 : 1]);
            $rates[trim($cols[0])] = (float) str_replace(',', '.', $rate);
        }

        $result = $service->importFidelioRates((int) $propertyId, $rates, CarbonImmutable::parse($this->option('from') ?? now()));
        $this->info(count($rates).' kod okundu, '.$result['linked'].' koda vergi bağlandı, vergi tanımları: '.implode(', ', array_keys($result['taxes'])));
        if ($result['unknown_codes']) {
            $this->warn('RS PMS\'te olmayan kodlar: '.implode(', ', $result['unknown_codes']));
        }

        return self::SUCCESS;
    }
}
