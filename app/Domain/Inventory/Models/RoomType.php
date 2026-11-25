<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_room_types — tasarım: docs/03-veritabani.md */
class RoomType extends Model
{
    use SoftDeletes;

    protected $table = 'pms_room_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_pseudo' => 'boolean',
            'is_active' => 'boolean',
            'is_bookable_online' => 'boolean',
        ];
    }

    public function roomClass(): BelongsTo
    {
        return $this->belongsTo(RoomClass::class, 'room_class_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }
}
