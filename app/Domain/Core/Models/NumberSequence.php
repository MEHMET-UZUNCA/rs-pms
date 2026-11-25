<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_number_sequences — tasarım: docs/03-veritabani.md */
class NumberSequence extends Model
{
    protected $table = 'pms_number_sequences';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reset_yearly' => 'boolean',
        ];
    }
}
