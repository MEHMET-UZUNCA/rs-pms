<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_taxes — tasarım: docs/03-veritabani.md */
class Tax extends Model
{
    use SoftDeletes;

    protected $table = 'pms_taxes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_inclusive' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }
}
