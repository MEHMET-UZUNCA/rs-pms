<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Reservations\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Rezervasyon kartının ek sekmeleri:
 *  - Features: oda tercihleri ve bu özelliklere sahip, konaklama boyunca boş odalar.
 *  - Credit Card: garanti kartı — yalnız kart sahibi, marka, son 4 hane ve son kullanma (PCI: kart numarası ve CVV saklanmaz;
 *    ileride sanal POS entegrasyonunda token sağlayıcının işlem anahtarıdır).
 *  - Inventory: sayılı kalemler (bebek yatağı, ek yatak …) — stok: kalemin toplam adedi, tarihleri çakışan rezervasyonlarla paylaşılır.
 */
class ReservationExtrasService
{
    public const CARD_BRANDS = ['visa', 'mastercard', 'amex', 'troy', 'other'];

    /** @param list<int> $featureIds */
    public function syncFeatures(Reservation $reservation, array $featureIds): void
    {
        DB::table('pms_reservation_features')->where('reservation_id', $reservation->id)->delete();
        foreach (array_unique(array_map('intval', $featureIds)) as $id) {
            DB::table('pms_reservation_features')->insert(['reservation_id' => $reservation->id, 'room_feature_id' => $id]);
        }
    }

    /** @return list<int> */
    public function features(Reservation $reservation): array
    {
        return DB::table('pms_reservation_features')->where('reservation_id', $reservation->id)->pluck('room_feature_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Oda tipinde, istenen bütün özelliklere sahip, arızasız ve konaklama boyunca başka rezervasyona atanmamış odalar.
     *
     * @param  list<int>  $featureIds
     * @return Collection<int, object{id: int, number: string, hk_status: string, fo_status: string, features: string}>
     */
    public function matchingRooms(int $propertyId, int $roomTypeId, string $arrival, string $departure, array $featureIds, ?int $exceptReservationId = null): Collection
    {
        $busy = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')
            ->whereNotNull('n.room_id')->where('n.stay_date', '>=', $arrival)->where('n.stay_date', '<', $departure)
            ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->when($exceptReservationId, fn ($q) => $q->where('r.id', '!=', $exceptReservationId))
            ->distinct()->pluck('n.room_id');
        $ooo = DB::table('pms_room_outages')->where('type', 'ooo')->whereNull('released_at')->where('date_from', '<', $departure)->where('date_to', '>', $arrival)->pluck('room_id');

        $rooms = DB::table('pms_rooms')->where('property_id', $propertyId)->where('room_type_id', $roomTypeId)->where('is_active', true)->where('is_pseudo', false)
            ->whereNotIn('id', $busy->merge($ooo)->all() ?: [0])
            ->when($featureIds, function ($q) use ($featureIds) {
                foreach ($featureIds as $f) {
                    $q->whereExists(fn ($e) => $e->selectRaw('1')->from('pms_room_feature_room as fr')->whereColumn('fr.room_id', 'pms_rooms.id')->where('fr.room_feature_id', $f));
                }
            })->orderBy('number')->limit(60)->get(['id', 'number', 'hk_status', 'fo_status']);
        $names = DB::table('pms_room_feature_room as fr')->join('pms_room_features as f', 'f.id', '=', 'fr.room_feature_id')->whereIn('fr.room_id', $rooms->pluck('id'))
            ->get(['fr.room_id', 'f.code'])->groupBy('room_id');

        return $rooms->map(fn ($r) => (object) ['id' => (int) $r->id, 'number' => $r->number, 'hk_status' => $r->hk_status, 'fo_status' => $r->fo_status,
            'features' => ($names[$r->id] ?? collect())->pluck('code')->sort()->implode(' ')]);
    }

    /** Garanti kartı: kaydet / değiştir / sil. @param array{card_holder?: ?string, card_brand?: ?string, card_last4?: ?string, card_expiry?: ?string, card_remove?: bool} $data */
    public function saveCard(Reservation $reservation, array $data): void
    {
        $current = $reservation->payment_token_id ? DB::table('pms_payment_tokens')->where('id', $reservation->payment_token_id)->first() : null;
        if (! empty($data['card_remove'])) {
            if ($current) {
                $reservation->update(['payment_token_id' => null]);
                DB::table('pms_payment_tokens')->where('id', $current->id)->delete();
            }

            return;
        }
        if (empty($data['card_last4'])) {
            return;   // kart girilmedi; varsa eskisi kalır
        }
        [$month, $year] = isset($data['card_expiry']) && preg_match('#^(\d{2})/(\d{2})$#', $data['card_expiry'], $m) ? [(int) $m[1], 2000 + (int) $m[2]] : [null, null];
        $values = ['profile_id' => $reservation->profile_id, 'reservation_id' => $reservation->id, 'provider' => 'manual',
            'token' => Crypt::encryptString('manual:'.$data['card_last4']), 'brand' => $data['card_brand'] ?? null, 'last4' => $data['card_last4'],
            'expiry_month' => $month, 'expiry_year' => $year, 'holder_name' => $data['card_holder'] ?? null, 'updated_at' => now()];
        if ($current) {
            DB::table('pms_payment_tokens')->where('id', $current->id)->update($values);
        } else {
            $reservation->update(['payment_token_id' => DB::table('pms_payment_tokens')->insertGetId($values + ['created_at' => now()])]);
        }
    }

    /** Kalemin tarih aralığında boşta kalan adedi. */
    public function itemAvailable(int $itemId, string $from, string $to, ?int $exceptReservationItemId = null): int
    {
        $total = (int) DB::table('pms_items')->where('id', $itemId)->value('quantity_total');
        $used = 0;
        $rows = DB::table('pms_reservation_items as ri')->join('pms_reservations as r', 'r.id', '=', 'ri.reservation_id')->where('ri.item_id', $itemId)
            ->whereIn('r.status', ['confirmed', 'tentative', 'in_house'])->where('ri.date_from', '<', $to)->where('ri.date_to', '>', $from)
            ->when($exceptReservationItemId, fn ($q) => $q->where('ri.id', '!=', $exceptReservationItemId))->get(['ri.quantity', 'ri.date_from', 'ri.date_to']);
        // En yoğun gece
        for ($d = $from; $d < $to; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
            $used = max($used, (int) $rows->filter(fn ($r) => substr((string) $r->date_from, 0, 10) <= $d && substr((string) $r->date_to, 0, 10) > $d)->sum('quantity'));
        }

        return max(0, $total - $used);
    }
}
