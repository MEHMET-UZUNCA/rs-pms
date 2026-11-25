<?php

namespace App\Domain\Core\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_taxes — tasarım: docs/03-veritabani.md */
class Tax extends Model
{
    use SoftDeletes;

    protected $table = 'pms_taxes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_inclusive' => 'boolean',
            'print_separately' => 'boolean',
            'post_zero' => 'boolean',
            'valid_from' => DateOnly::class,
            'valid_to' => DateOnly::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
