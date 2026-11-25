<?php

namespace App\Domain\Codes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_market_segments — tasarım: docs/03-veritabani.md */
class MarketSegment extends Model
{
    use SoftDeletes;

    protected $table = 'pms_market_segments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
