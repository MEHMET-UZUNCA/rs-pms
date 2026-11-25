<?php

namespace App\Domain\Reservations\Models;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Codes\Models\ReservationType;
use App\Domain\Inventory\Models\Room;
use App\Domain\Inventory\Models\RoomType;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Rates\Models\RateCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_reservations — tasarım: docs/03-veritabani.md */
class Reservation extends Model
{
    protected $table = 'pms_reservations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'departure_date' => 'date',
            'original_departure_date' => 'date',
            'sale_date' => 'date',
            'option_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'check_in_business_date' => 'date',
            'check_out_business_date' => 'date',
            'is_room_locked' => 'boolean',
            'is_complimentary' => 'boolean',
            'is_house_use' => 'boolean',
            'is_walk_in' => 'boolean',
            'is_day_use' => 'boolean',
            'room_charges_allowed' => 'boolean',
            'child_ages' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class, 'reservation_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function rateCode(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'rate_code_id');
    }

    public function reservationType(): BelongsTo
    {
        return $this->belongsTo(ReservationType::class, 'reservation_type_id');
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class, 'reservation_id');
    }
}
