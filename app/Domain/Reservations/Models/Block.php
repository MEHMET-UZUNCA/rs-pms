<?php

namespace App\Domain\Reservations\Models;

use App\Support\Audit\Audited;
use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_blocks — tasarım: docs/03-veritabani.md */
class Block extends Model
{
    use Audited;
    use SoftDeletes;

    protected $table = 'pms_blocks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'deducts_inventory' => 'boolean',
        ];
    }
}
