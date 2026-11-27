<?php

namespace App\Domain\Reservations\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fidelio Global Settings → Reservation (5 Rates → Reservation 1) karşılığı. Varsayılanlar Rocks Hotel'in Fidelio ayarlarından
 * (26.11.2026). pms_settings "reservation.options" anahtarında saklanır; Ayarlar → Rezervasyon seçenekleri ekranından değişir.
 * Otelin kullanmadığı seçenekler (turnaway, party, room owner …) alınmadı.
 */
class ReservationOptions
{
    public const KEY = 'reservation.options';

    /** Oda durumu seçenekleri: "none" = No Action. */
    public const ROOM_STATUSES = ['none', 'dirty', 'clean', 'inspected'];

    /** Kirli (Dirty / Pick Up) odaya giriş: izin ver / uyar / engelle. */
    public const DIRTY_CHECK_IN = ['allow', 'warn', 'block'];

    public const DEFAULTS = [
        'check_in_time' => '14:00', 'check_out_time' => '12:00',
        'day_use_check_in_time' => '14:00', 'day_use_check_out_time' => '18:00',
        // Rocks (otel teyidi): oda yalnız check-out'ta (ve oda değişiminde eski oda) Dirty olur; girişte ve gece kapanışında
        // kat durumu değişmez, onu housekeeping / resepsiyon değiştirir.
        'status_after_check_in' => 'none',           // Room Status after Check In
        'status_after_check_out' => 'dirty',         // Room Status after Check Out
        'status_after_audit_occupied' => 'none',     // Room Status after N/A if occupied
        'status_after_no_show' => 'none',            // Room Status after No Show
        'dirty_room_check_in' => 'warn',             // kirli odaya giriş (Elektraweb parametresi): allow / warn / block
        // Web sitesinden gelen rezervasyonlar (Web API POST /reservations): kodlar boşsa web rezervasyonu kapalıdır
        'web_reservation_type' => null, 'web_market' => null, 'web_source' => null, 'web_status' => 'confirmed',
        'market_code_mandatory' => true,
        'queue_rooms' => true,                       // Queue Rooms Handling
        'meal_plan' => true,                         // Meal plan functionality
        // Meal Plan defaults: ilk gün / çıkış günü / günübirlik rezervasyonda hangi öğünler sayılır
        'meals_first_day' => ['breakfast' => false, 'lunch' => false, 'dinner' => true],
        'meals_departure_day' => ['breakfast' => true, 'lunch' => true, 'dinner' => false],
        'meals_day_use' => ['breakfast' => false, 'lunch' => true, 'dinner' => false],
    ];

    /** @return array<string, mixed> */
    public static function get(int $propertyId): array
    {
        $stored = DB::table('pms_settings')->where('property_id', $propertyId)->where('key', self::KEY)->value('value');
        $stored = is_string($stored) ? (array) json_decode($stored, true) : [];

        return array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    /** @param array<string, mixed> $options */
    public static function save(int $propertyId, array $options, ?int $userId): void
    {
        $value = array_replace(self::get($propertyId), array_intersect_key($options, self::DEFAULTS));
        DB::table('pms_settings')->updateOrInsert(['property_id' => $propertyId, 'key' => self::KEY],
            ['value' => json_encode($value), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Ayardaki oda durumu; "none" ise null (dokunulmaz). */
    public static function roomStatus(int $propertyId, string $option): ?string
    {
        $status = self::get($propertyId)[$option] ?? 'none';

        return $status === 'none' ? null : $status;
    }
}
