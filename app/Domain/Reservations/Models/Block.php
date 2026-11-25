<?php

namespace App\Domain\Reservations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_blocks — tasarım: docs/03-veritabani.md */
class Block extends Model
{
    use SoftDeletes;

    protected $table = 'pms_blocks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'deducts_inventory' => 'boolean',
        ];
    }
}
