<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_currencies — tasarım: docs/03-veritabani.md */
class Currency extends Model
{
    protected $table = 'pms_currencies';

    protected $guarded = ['id'];

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
