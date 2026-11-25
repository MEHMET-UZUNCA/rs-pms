<?php

namespace App\Domain\Rates\Models;

use App\Domain\Inventory\Models\RoomType;
use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_inventory_daily — tasarım: docs/03-veritabani.md */
class InventoryDaily extends Model
{
    protected $table = 'pms_inventory_daily';

    protected $guarded = ['id'];

    public const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }
}
