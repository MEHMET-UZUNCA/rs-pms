<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Boşluksuz numara serileri (fatura, folyo, onay no …). Satır kilidiyle alınır; çağıran işlem geri alınırsa numara da geri alınır.
 */
class NumberSequenceService
{
    public function next(int $propertyId, string $type, ?int $year = null): string
    {
        return DB::transaction(function () use ($propertyId, $type, $year) {
            $year ??= (int) now()->format('Y');
            $sequence = NumberSequence::query()
                ->where('property_id', $propertyId)->where('type', $type)
                ->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'property_id' => $propertyId, 'type' => $type, 'next_number' => 1, 'padding' => 6, 'current_year' => $year,
                ]);
                $sequence = NumberSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
            }

            if ($sequence->reset_yearly && (int) $sequence->current_year !== $year) {
                $sequence->next_number = 1;
                $sequence->current_year = $year;
            }

            $number = (int) $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return ($sequence->prefix ?? '').str_pad((string) $number, (int) $sequence->padding, '0', STR_PAD_LEFT);
        });
    }
}
