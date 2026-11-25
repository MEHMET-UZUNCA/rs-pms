<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_rooms — tasarım: docs/03-veritabani.md */
class Room extends Model
{
    use SoftDeletes;

    protected $table = 'pms_rooms';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_pseudo' => 'boolean',
            'is_active' => 'boolean',
            'hk_status_changed_at' => 'datetime',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }
}
