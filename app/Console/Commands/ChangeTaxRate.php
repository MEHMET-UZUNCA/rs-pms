<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\Tax;
use App\Domain\Core\Services\TaxRateService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Vergi oranını belirtilen günden itibaren değiştirir; geçmiş kayıtlar eski oranla kalır. */
class ChangeTaxRate extends Command
{
    protected $signature = 'pms:change-tax-rate {code : Vergi kodu (ör. KDV10)} {rate : Yeni oran (%)} {--from= : Başlangıç (YYYY-AA-GG)} {--property=RCK}';

    protected $description = 'Vergi oranını tarihli olarak değiştirir';

    public function handle(TaxRateService $service): int
    {
        $tax = Tax::query()->whereHas('property', fn ($q) => $q->where('code', $this->option('property')))
            ->where('code', $this->argument('code'))->whereNull('valid_to')->first();
        if (! $tax) {
            $this->error('Geçerli vergi bulunamadı.');

            return self::FAILURE;
        }
        $new = $service->changeRate($tax, (float) str_replace(',', '.', $this->argument('rate')), CarbonImmutable::parse($this->option('from') ?? now()));
        $this->info("{$new->code}: %{$tax->rate} → %{$new->rate}, {$new->valid_from->toDateString()} tarihinden itibaren.");

        return self::SUCCESS;
    }
}
