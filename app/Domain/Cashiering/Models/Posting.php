<?php

namespace App\Domain\Cashiering\Models;

use App\Domain\Rates\Models\TransactionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_postings — tasarım: docs/03-veritabani.md */
class Posting extends Model
{
    protected $table = 'pms_postings';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'stay_date' => 'date',
            'amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class, 'folio_id');
    }

    public function transactionCode(): BelongsTo
    {
        return $this->belongsTo(TransactionCode::class, 'transaction_code_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(PostingTax::class, 'posting_id');
    }
}
