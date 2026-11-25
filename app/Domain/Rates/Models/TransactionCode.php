<?php

namespace App\Domain\Rates\Models;

use App\Domain\Core\Models\Tax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_transaction_codes — tasarım: docs/03-veritabani.md */
class TransactionCode extends Model
{
    use SoftDeletes;

    protected $table = 'pms_transaction_codes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_discount' => 'boolean',
            'is_no_show' => 'boolean',
            'is_tax_inclusive' => 'boolean',
            'manual_post_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TransactionCodeGroup::class, 'group_id');
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'pms_transaction_code_taxes', 'transaction_code_id', 'tax_id');
    }
}
