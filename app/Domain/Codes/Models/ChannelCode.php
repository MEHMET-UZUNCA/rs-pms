<?php

namespace App\Domain\Codes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_channel_codes — tasarım: docs/03-veritabani.md */
class ChannelCode extends Model
{
    use SoftDeletes;

    protected $table = 'pms_channel_codes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
