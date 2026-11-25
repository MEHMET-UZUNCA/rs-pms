<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_rate_amounts — tasarım: docs/03-veritabani.md */
class RateAmount extends Model
{
    protected $table = 'pms_rate_amounts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function rateCode(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'rate_code_id');
    }
}
