<?php

namespace App\Domain\Reservations\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_reservation_nights — tasarım: docs/03-veritabani.md */
class ReservationNight extends Model
{
    protected $table = 'pms_reservation_nights';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'package_amount' => 'decimal:2',
            'package_included_amount' => 'decimal:2',
            'manual_amount' => 'decimal:2',
            'stay_date' => DateOnly::class,
            'is_complimentary' => 'boolean',
            'is_house_use' => 'boolean',
            'is_overridden' => 'boolean',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
