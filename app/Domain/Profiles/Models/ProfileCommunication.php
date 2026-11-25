<?php

namespace App\Domain\Profiles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_profile_communications — tasarım: docs/03-veritabani.md */
class ProfileCommunication extends Model
{
    protected $table = 'pms_profile_communications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'marketing_allowed' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
