<?php

namespace App\Domain\Housekeeping\Models;

use App\Domain\Inventory\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_lost_found — kayıp (lost) ya da bulunan (found) eşya. Durum: open / matched / returned / disposed. */
class LostFoundItem extends Model
{
    protected $table = 'pms_lost_found';

    protected $guarded = ['id'];

    public const STATUSES = ['open', 'matched', 'returned', 'disposed'];

    protected function casts(): array
    {
        return ['found_at' => 'datetime', 'returned_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matched_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LostFoundAction::class, 'lost_found_id')->orderByDesc('id');
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['returned', 'disposed'], true);
    }
}
