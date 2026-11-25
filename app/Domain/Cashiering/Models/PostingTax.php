<?php

namespace App\Domain\Cashiering\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_posting_taxes — tasarım: docs/03-veritabani.md */
class PostingTax extends Model
{
    protected $table = 'pms_posting_taxes';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'base_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }
}
