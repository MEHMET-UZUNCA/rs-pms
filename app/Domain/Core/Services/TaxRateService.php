<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Models\Tax;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Vergi oranı yönetimi. Oranlar tarihlidir: değişiklik geçmişi bozmaz.
 *  - changeRate(): eski sürümü (gün − 1) ile kapatır, aynı koda yeni oranlı sürüm açar ve işlem kodu bağlantılarını kopyalar.
 *  - importFidelioRates(): Fidelio kayıtlarından hesaplanan "departman kodu → oran" listesini vergi tanımlarına çevirir.
 */
class TaxRateService
{
    public function changeRate(Tax $tax, float $newRate, CarbonInterface $from, ?string $name = null): Tax
    {
        if ($newRate < 0 || $newRate > 100) {
            throw new InvalidArgumentException('Vergi oranı 0 ile 100 arasında olmalı.');
        }
        $from = CarbonImmutable::parse($from)->startOfDay();
        if ($tax->valid_from && $from->lte($tax->valid_from)) {
            throw new InvalidArgumentException('Yeni oranın başlangıcı mevcut sürümün başlangıcından sonra olmalı.');
        }

        return DB::transaction(function () use ($tax, $newRate, $from, $name) {
            $links = DB::table('pms_transaction_code_taxes')->where('tax_id', $tax->id)->get();
            $tax->update(['valid_to' => $from->subDay()->toDateString()]);

            $new = $tax->replicate(['valid_to']);
            $new->fill(['rate' => $newRate, 'valid_from' => $from->toDateString(), 'valid_to' => null, 'name' => $name ?? $this->name($tax->code, $newRate)]);
            $new->save();

            foreach ($links as $link) {
                DB::table('pms_transaction_code_taxes')->insert([
                    'transaction_code_id' => $link->transaction_code_id, 'tax_id' => $new->id, 'sequence' => $link->sequence, 'calc_base' => $link->calc_base,
                ]);
            }

            return $new;
        });
    }

    /**
     * @param  array<string, float>  $rates  Fidelio departman kodu => yüzde oran (ör. ['100' => 10.0, '320' => 20.0])
     * @return array{taxes: array<string,int>, linked: int, unknown_codes: array<int,string>}
     */
    public function importFidelioRates(int $propertyId, array $rates, CarbonInterface $validFrom): array
    {
        return DB::transaction(function () use ($propertyId, $rates, $validFrom) {
            $taxes = [];
            $linked = 0;
            $unknown = [];
            foreach ($rates as $dcNumber => $rate) {
                $codeId = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', (string) $dcNumber)->value('id');
                if (! $codeId) {
                    $unknown[] = (string) $dcNumber;

                    continue;
                }
                $rate = $this->normalize((float) $rate);
                DB::table('pms_transaction_code_taxes')->where('transaction_code_id', $codeId)
                    ->whereIn('tax_id', Tax::query()->where('property_id', $propertyId)->where('source', 'fidelio')->pluck('id'))->delete();
                // Önce Fidelio "Tax Details"ten gelen ve bugün geçerli olan aynı oranlı vergi kullanılır; yoksa yenisi açılır.
                $existing = Tax::query()->where('property_id', $propertyId)->where('calc_type', 'percent')->where('rate', $rate)
                    ->whereNull('valid_to')->orderByRaw("CASE WHEN source = 'fidelio' THEN 0 ELSE 1 END")->value('id');
                $key = $existing ? (string) Tax::query()->whereKey($existing)->value('code') : $this->code($rate);
                $taxes[$key] ??= $existing ?? Tax::query()->firstOrCreate(
                    ['property_id' => $propertyId, 'code' => $key, 'valid_from' => CarbonImmutable::parse($validFrom)->toDateString()],
                    ['name' => $this->name($key, $rate), 'calc_type' => 'percent', 'rate' => $rate, 'is_inclusive' => true, 'source' => 'fidelio']
                )->id;
                DB::table('pms_transaction_code_taxes')->insert(['transaction_code_id' => $codeId, 'tax_id' => $taxes[$key], 'sequence' => 1, 'calc_base' => 'net']);
                $linked++;
            }

            return ['taxes' => $taxes, 'linked' => $linked, 'unknown_codes' => $unknown];
        });
    }

    /** Kayıtlardan hesaplanan oranlar küsuratlı çıkabilir (9,998 gibi): tam sayıya 0,05 yakınsa tam sayıya yuvarlanır. */
    public function normalize(float $rate): float
    {
        $rounded = round($rate);

        return abs($rate - $rounded) <= 0.05 ? (float) $rounded : round($rate, 2);
    }

    private function code(float $rate): string
    {
        return 'VAT'.rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private function name(string $code, float $rate): string
    {
        return 'KDV %'.rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
    }
}
