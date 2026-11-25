<?php

namespace App\Domain\Profiles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_profiles — tasarım: docs/03-veritabani.md */
class Profile extends Model
{
    use SoftDeletes;

    protected $table = 'pms_profiles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'wedding_date' => 'date',
            'marketing_consent' => 'boolean',
            'no_mailing' => 'boolean',
            'no_emailing' => 'boolean',
            'store_private_data' => 'boolean',
            'merged_at' => 'datetime',
        ];
    }

    public function communications(): HasMany
    {
        return $this->hasMany(ProfileCommunication::class, 'profile_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ProfileAddress::class, 'profile_id');
    }
}
