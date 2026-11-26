<?php

namespace App\Domain\Rates\Services;

use App\Domain\Rates\Models\RateAmount;
use App\Domain\Rates\Models\RateCode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ayarlar → Fiyat kodları (Fidelio Rates / Rate Edit). Fiyat kodu başlığı ve fiyat detayları (dönem, oda tipleri, odalar, fiyatlar).
 * Kullanılmış fiyat kodu silinmez, pasif yapılır (geçmiş rezervasyonların fiyatı bozulmaz).
 */
class RateCodeService
{
    /** Fiyat detayındaki fiyat alanları (Prices sekmesi). */
    public const PRICE_FIELDS = ['price_default', 'price_1', 'price_2', 'price_3', 'price_4', 'extra_adult', 'extra_bed',
        'child_band_1', 'child_band_2', 'child_band_3',
        'weekend_price_default', 'weekend_price_1', 'weekend_price_2', 'weekend_price_3', 'weekend_price_4',
        'weekend_child_band_1', 'weekend_child_band_2', 'weekend_child_band_3'];

    /** @param array<string, mixed> $data */
    public function save(int $propertyId, array $data, ?RateCode $rate): RateCode
    {
        return DB::transaction(function () use ($propertyId, $data, $rate) {
            if (! empty($data['new_category'])) {
                $code = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $data['new_category']) ?: 'CAT', 0, 10));
                $data['rate_category_id'] = DB::table('pms_rate_categories')->where('property_id', $propertyId)->where('code', $code)->value('id')
                    ?? DB::table('pms_rate_categories')->insertGetId(['property_id' => $propertyId, 'code' => $code, 'name' => $data['new_category'],
                        'sort' => (int) DB::table('pms_rate_categories')->where('property_id', $propertyId)->max('sort') + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            unset($data['new_category']);

            if ($rate) {
                $rate->update($data);

                return $rate->fresh();
            }
            $data['sort'] = (int) RateCode::where('property_id', $propertyId)->where('rate_category_id', $data['rate_category_id'] ?? null)->max('sort') + 1;

            return RateCode::create($data + ['property_id' => $propertyId]);
        });
    }

    /** Fidelio "Copy": başlık ve bütün fiyat detayları yeni koda kopyalanır. */
    public function copy(RateCode $rate, string $code, string $name): RateCode
    {
        return DB::transaction(function () use ($rate, $code, $name) {
            $copy = $rate->replicate(['legacy_id']);
            $copy->fill(['code' => $code, 'name' => $name, 'sort' => $rate->sort + 1])->save();
            foreach ($rate->amounts()->with('roomTypes')->get() as $amount) {
                $new = $amount->replicate();
                $new->rate_code_id = $copy->id;
                $new->save();
                $new->roomTypes()->sync($amount->roomTypes->pluck('id'));
            }

            return $copy;
        });
    }

    public function delete(RateCode $rate): void
    {
        $used = DB::table('pms_reservations')->where('rate_code_id', $rate->id)->exists()
            || DB::table('pms_reservation_nights')->where('rate_code_id', $rate->id)->exists();
        if ($used) {
            throw new RuntimeException(__('pms.rates.err_in_use'));
        }
        DB::transaction(function () use ($rate) {
            $rate->amounts()->delete();
            DB::table('pms_rate_code_room_types')->where('rate_code_id', $rate->id)->delete();
            $rate->forceDelete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $roomTypeIds  boşsa bütün oda tipleri
     */
    public function saveDetail(RateCode $rate, array $data, array $roomTypeIds, ?RateAmount $detail, ?int $userId): RateAmount
    {
        return DB::transaction(function () use ($rate, $data, $roomTypeIds, $detail, $userId) {
            $data['room_type_id'] = null;
            if ($detail) {
                $detail->update($data);
            } else {
                $detail = RateAmount::create($data + ['rate_code_id' => $rate->id, 'created_by' => $userId]);
            }
            $detail->roomTypes()->sync($roomTypeIds);

            return $detail->fresh('roomTypes');
        });
    }
}
