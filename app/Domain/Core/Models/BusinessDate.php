<?php

namespace App\Domain\Core\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_business_dates — tasarım: docs/03-veritabani.md */
class BusinessDate extends Model
{
    protected $table = 'pms_business_dates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => DateOnly::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
