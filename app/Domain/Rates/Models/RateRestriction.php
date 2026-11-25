<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_rate_restrictions — tasarım: docs/03-veritabani.md */
class RateRestriction extends Model
{
    protected $table = 'pms_rate_restrictions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
        ];
    }
}
