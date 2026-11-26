<?php

namespace App\Domain\Rates\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_rate_codes — tasarım: docs/03-veritabani.md */
class RateCode extends Model
{
    use SoftDeletes;

    protected $table = 'pms_rate_codes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_tax_inclusive' => 'boolean',
            'is_public' => 'boolean',
            'is_negotiated' => 'boolean',
            'is_active' => 'boolean',
            'is_fixed_rate' => 'boolean',
            'is_flat_rate' => 'boolean',
            'print_rate' => 'boolean',
            'refresh_exchange_at_checkin' => 'boolean',
            'valid_from' => DateOnly::class,
            'valid_to' => DateOnly::class,
            'derive_value' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'parent_rate_code_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function roomTransactionCode(): BelongsTo
    {
        return $this->belongsTo(TransactionCode::class, 'room_transaction_code_id');
    }

    public function amounts(): HasMany
    {
        return $this->hasMany(RateAmount::class, 'rate_code_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RateCategory::class, 'rate_category_id');
    }
}
