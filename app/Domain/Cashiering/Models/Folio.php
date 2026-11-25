<?php

namespace App\Domain\Cashiering\Models;

use App\Domain\Reservations\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_folios — tasarım: docs/03-veritabani.md */
class Folio extends Model
{
    protected $table = 'pms_folios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'opened_business_date' => 'date',
            'closed_business_date' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class, 'folio_id');
    }
}
