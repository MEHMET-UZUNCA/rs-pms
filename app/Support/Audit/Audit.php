<?php

namespace App\Support\Audit;

use App\Domain\Core\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Değişiklik kaydı (pms_audit_logs): kim, ne zaman, hangi kayıtta neyi neyden neye değiştirdi. Eloquent modelleri Audited trait'iyle
 * kendiliğinden yazar; DB::table ile yapılan önemli işlemler (rol yetkileri, overbooking, profil birleştirme …) Audit::record ile yazılır.
 * Kayıt başarısız olursa asıl işlem durmaz (denetim kaydı yan iştir).
 */
class Audit
{
    /** Kaydı tutulmayan alanlar (gürültü). */
    public const IGNORE = ['updated_at', 'created_at', 'updated_by', 'remember_token', 'last_login_at', 'balance', 'password', 'preferences', 'token', 'token_hash', 'pin_hash'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(string $type, int $id, string $action, ?array $old = null, ?array $new = null, ?string $note = null, ?int $propertyId = null): void
    {
        try {
            $property = $propertyId ?? (app()->bound(Property::class) ? app(Property::class)->id : null);
            $request = app()->runningInConsole() ? null : request();
            DB::table('pms_audit_logs')->insert([
                'property_id' => $property, 'user_id' => auth()->id(), 'auditable_type' => $type, 'auditable_id' => $id, 'action' => $action,
                'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null, 'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'note' => $note ? mb_substr($note, 0, 500) : null,
                'business_date' => $property ? DB::table('pms_properties')->where('id', $property)->value('current_business_date') : null,
                'ip' => $request?->ip(), 'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null, 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Kısa tip adı (App\Domain\Reservations\Models\Reservation → reservation). */
    public static function typeOf(object|string $model): string
    {
        return Str::snake(class_basename($model));
    }
}
