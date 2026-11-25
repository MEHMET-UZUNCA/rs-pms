<?php

namespace App\Support\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Yalnız tarih (YYYY-AA-GG) saklar, CarbonImmutable döndürür.
 * Laravel'in "date" dönüşümü değeri saatle birlikte yazar; SQLite'ta "2026-12-01 00:00:00" olarak kalır ve
 * iş günü / konaklama günü karşılaştırmalarını bozar. Bütün iş günü ve konaklama tarihleri bu dönüşümle tutulur.
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse($value)->toDateString();
    }
}
