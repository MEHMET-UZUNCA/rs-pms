<?php

namespace App\Domain\Rates\Services;

/** Bir gecenin fiyat hesabı sonucu. */
final class RateQuote
{
    public function __construct(
        public readonly ?float $amount,          // Brüt gece fiyatı (paket dahil); fiyat bulunamazsa null
        public readonly float $packageAmount,    // Paket elemanlarının toplamı (kahvaltı, akşam yemeği …)
        public readonly ?int $rateAmountId,      // Kullanılan fiyat satırı
        public readonly string $source,          // table / derived / none
    ) {}

    public function found(): bool
    {
        return $this->amount !== null;
    }
}
