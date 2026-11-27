<?php

namespace App\Domain\Inventory\Models;

use App\Support\Audit\Audited;
use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_room_outages — tasarım: docs/03-veritabani.md */
class RoomOutage extends Model
{
    use Audited;

    protected $table = 'pms_room_outages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_from' => DateOnly::class,
            'date_to' => DateOnly::class,
            'released_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
