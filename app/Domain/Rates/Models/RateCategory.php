<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_rate_categories (Fidelio Rate Categories: Rack Rates, Agent Rates, 2027 FIYATLARI …). */
class RateCategory extends Model
{
    protected $table = 'pms_rate_categories';

    protected $guarded = ['id'];
}
