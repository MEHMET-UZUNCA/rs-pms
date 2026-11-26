<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_seasons (Fidelio Rate Seasons). */
class Season extends Model
{
    use SoftDeletes;

    protected $table = 'pms_seasons';

    protected $guarded = ['id'];
}
