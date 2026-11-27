<?php

namespace App\Domain\Cashiering\Models;

use App\Domain\Reservations\Models\Reservation;
use App\Support\Audit\Audited;
use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_folios — tasarım: docs/03-veritabani.md */
class Folio extends Model
{
    use Audited;

    protected $table = 'pms_folios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'no_post' => 'boolean',
            'opened_business_date' => DateOnly::class,
            'closed_business_date' => DateOnly::class,
            'valid_from' => DateOnly::class,
            'actual_from' => DateOnly::class,
            'actual_until' => DateOnly::class,
            'valid_until' => DateOnly::class,
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
