<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Fidelio tarih kutusu girişini çözer (docs/07-arayuz.md §7.1): "t" = iş günü, "+1" / "-7" = iş gününe göre gün,
 * "25.11.2026", "25.11.26", "25.11" (iş gününün yılı), "25" (iş gününün ay/yılı), ISO "2026-11-25". Çözülemezse null.
 */
class BusinessDateInput
{
    public static function parse(?string $value, CarbonImmutable $businessDate): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (in_array(mb_strtolower($value), ['t', 'b', 'bugün', 'bugun'], true)) {
            return $businessDate;
        }
        if (preg_match('/^([+-])\s*(\d{1,4})$/', $value, $m)) {
            return $m[1] === '+' ? $businessDate->addDays((int) $m[2]) : $businessDate->subDays((int) $m[2]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]) : null;
        }
        if (preg_match('/^(\d{1,2})(?:[.\/](\d{1,2})(?:[.\/](\d{2}|\d{4}))?)?$/', $value, $m)) {
            $day = (int) $m[1];
            $month = isset($m[2]) ? (int) $m[2] : $businessDate->month;
            $year = isset($m[3]) ? (int) (strlen($m[3]) === 2 ? '20'.$m[3] : $m[3]) : $businessDate->year;

            return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day) : null;
        }

        return null;
    }
}
