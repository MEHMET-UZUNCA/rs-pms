<?php

namespace App\Domain\Core\Models;

use App\Domain\Inventory\Models\Room;
use App\Domain\Inventory\Models\RoomType;
use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_properties — tasarım: docs/03-veritabani.md */
class Property extends Model
{
    use SoftDeletes;

    protected $table = 'pms_properties';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_business_date' => DateOnly::class,
            'is_active' => 'boolean',
        ];
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'property_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'property_id');
    }
}
