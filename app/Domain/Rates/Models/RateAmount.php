<?php

namespace App\Domain\Rates\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Inventory\Models\RoomType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tablo: pms_rate_amounts — tasarım: docs/03-veritabani.md */
class RateAmount extends Model
{
    protected $table = 'pms_rate_amounts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_from' => DateOnly::class,
            'date_to' => DateOnly::class,
        ];
    }

    public function rateCode(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'rate_code_id');
    }

    /** Fidelio "Room types" (birden çok); boşsa ve room_type_id de boşsa bütün oda tipleri. */
    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'pms_rate_amount_room_types', 'rate_amount_id', 'room_type_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
