<?php

namespace App\Domain\Housekeeping\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_lost_found_actions — eşyanın hareket geçmişi. */
class LostFoundAction extends Model
{
    protected $table = 'pms_lost_found_actions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
