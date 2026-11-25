<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_packages — tasarım: docs/03-veritabani.md */
class Package extends Model
{
    use SoftDeletes;

    protected $table = 'pms_packages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_included_in_rate' => 'boolean',
        ];
    }

    public function elements(): HasMany
    {
        return $this->hasMany(PackageElement::class, 'package_id');
    }
}
