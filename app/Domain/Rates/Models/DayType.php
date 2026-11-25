<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_day_types — tasarım: docs/03-veritabani.md */
class DayType extends Model
{
    protected $table = 'pms_day_types';

    protected $guarded = ['id'];
}
