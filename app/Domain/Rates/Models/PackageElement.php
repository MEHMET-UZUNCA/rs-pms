<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tablo: pms_package_elements — tasarım: docs/03-veritabani.md */
class PackageElement extends Model
{
    protected $table = 'pms_package_elements';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_allowance' => 'boolean',
            'amount_adult' => 'decimal:2',
            'amount_child' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function transactionCode(): BelongsTo
    {
        return $this->belongsTo(TransactionCode::class, 'transaction_code_id');
    }
}
