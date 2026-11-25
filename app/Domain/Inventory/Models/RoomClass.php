<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_room_classes — tasarım: docs/03-veritabani.md */
class RoomClass extends Model
{
    use SoftDeletes;

    protected $table = 'pms_room_classes';

    protected $guarded = ['id'];
}
