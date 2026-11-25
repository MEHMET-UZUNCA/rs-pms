<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_exchange_rates — tasarım: docs/03-veritabani.md */
class ExchangeRate extends Model
{
    protected $table = 'pms_exchange_rates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'posting_rate' => 'decimal:8',
            'buy_rate' => 'decimal:8',
            'sell_rate' => 'decimal:8',
        ];
    }
}
