<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent modeline eklenince oluşturma, değiştirme (yalnız değişen alanlar, eski → yeni) ve silme pms_audit_logs'a yazılır.
 * Model $auditExclude ile kendi gürültülü alanlarını dışarıda bırakabilir.
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(fn (Model $m) => Audit::record(Audit::typeOf($m), (int) $m->getKey(), 'created', null, $m->auditable($m->getAttributes()), null, $m->getAttribute('property_id')));
        static::updated(function (Model $m) {
            $changes = $m->auditable($m->getChanges());
            if (! $changes) {
                return;
            }
            $old = array_intersect_key($m->getRawOriginal(), $changes);
            $action = array_key_exists('status', $changes) ? 'status_changed' : 'updated';
            Audit::record(Audit::typeOf($m), (int) $m->getKey(), $action, $old, $changes, null, $m->getAttribute('property_id'));
        });
        static::deleted(fn (Model $m) => Audit::record(Audit::typeOf($m), (int) $m->getKey(), 'deleted', $m->auditable($m->getRawOriginal()), null, null, $m->getAttribute('property_id')));
    }

    /** @param array<string, mixed> $values */
    public function auditable(array $values): array
    {
        return array_diff_key($values, array_flip(array_merge(Audit::IGNORE, $this->auditExclude ?? [])));
    }
}
