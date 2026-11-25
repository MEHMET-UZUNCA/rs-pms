<?php

namespace App\Domain\Codes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_reservation_types — tasarım: docs/03-veritabani.md */
class ReservationType extends Model
{
    use SoftDeletes;

    protected $table = 'pms_reservation_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deducts_inventory' => 'boolean',
            'is_guaranteed' => 'boolean',
            'requires_deposit' => 'boolean',
        ];
    }
}
