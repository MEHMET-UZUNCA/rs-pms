<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_reason_codes — tasarım: docs/03-veritabani.md */
class ReasonCode extends Model
{
    protected $table = 'pms_reason_codes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requires_note' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
