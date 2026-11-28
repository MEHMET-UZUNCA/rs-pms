<?php

namespace App\Support;

use App\Domain\Core\Models\Property;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Giriş ekranının görünümü (Ayarlar → Giriş ekranı): arka plan görseli, karartma oranı, karşılama başlığı ve metni.
 * Görsel storage/app/private/branding/ altında durur, herkese açık /giris/arka-plan adresinden verilir (storage:link gerekmez).
 * pms_settings "login.appearance" anahtarında.
 */
class LoginAppearance
{
    public const KEY = 'login.appearance';

    public const DISK = 'local';

    public const DIR = 'branding';

    public const DEFAULTS = ['image' => null, 'image_version' => null, 'overlay' => 45, 'title' => null, 'subtitle' => null];

    /** Giriş ekranında tesis henüz seçilmemiştir; tek tesisli kurulumda ilk tesis. */
    public static function property(): ?Property
    {
        return Property::query()->orderBy('id')->first();
    }

    /** @return array<string, mixed> */
    public static function get(?int $propertyId = null): array
    {
        $propertyId ??= self::property()?->id;
        $stored = $propertyId ? DB::table('pms_settings')->where('property_id', $propertyId)->where('key', self::KEY)->value('value') : null;
        $stored = is_string($stored) ? (array) json_decode($stored, true) : [];
        $value = array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        if ($value['image'] && ! Storage::disk(self::DISK)->exists($value['image'])) {
            $value['image'] = null;
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    public static function save(int $propertyId, array $values, ?int $userId): void
    {
        $old = self::get($propertyId);
        $value = array_replace($old, array_intersect_key($values, self::DEFAULTS));
        $changed = array_filter($value, fn ($v, $k) => ($old[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);
        if ($changed) {
            Audit::record('settings', $propertyId, 'updated', array_intersect_key($old, $changed), $changed, self::KEY, $propertyId);
        }
        DB::table('pms_settings')->updateOrInsert(['property_id' => $propertyId, 'key' => self::KEY],
            ['value' => json_encode($value), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
    }
}
