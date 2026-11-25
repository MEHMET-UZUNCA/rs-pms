<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_countries — tasarım: docs/03-veritabani.md */
class Country extends Model
{
    protected $table = 'pms_countries';

    protected $guarded = ['id'];

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;
}
