<?php

namespace App\Console\Commands;

use App\Domain\Core\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Günlük kur servisi: seçili kaynaktan (TCMB / KKTC Merkez Bankası) kurları okur, otel kurlarını kurallara göre veritabanına yazar.
 * Zamanlayıcıda (routes/console.php) her gün çalışır; sunucuda `php artisan schedule:run` her dakika çalışmalı (cron).
 */
class FetchExchangeRates extends Command
{
    protected $signature = 'pms:fetch-exchange-rates {--property= : Tesis kodu (boşsa bütün tesisler)} {--file= : Bankadan indirmek yerine bu XML dosyası}';

    protected $description = 'Döviz kurlarını bankadan çeker ve otel kurlarını yazar';

    public function handle(ExchangeRateService $rates): int
    {
        $xml = $this->option('file') ? file_get_contents((string) $this->option('file')) : null;
        $properties = DB::table('pms_properties')->when($this->option('property'), fn ($q, $code) => $q->where('code', $code))->get(['id', 'code']);
        $failed = false;
        foreach ($properties as $property) {
            try {
                $count = $rates->fetch($property->id, $xml === false ? null : $xml);
                $this->info("{$property->code}: {$count} döviz kuru yazıldı (kaynak: ".ExchangeRateService::source($property->id).').');
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$property->code}: {$e->getMessage()}");
                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
