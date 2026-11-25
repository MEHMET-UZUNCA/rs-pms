<?php

namespace App\Domain\Pos\Models;

use App\Domain\Core\Models\Property;
use App\Domain\Rates\Models\TransactionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tablo: pms_outlets — Simphony'deki gelir merkezi (RVC) ya da RS ONE departmanı. Tasarım: docs/04-simphony.md §4.4 */
class Outlet extends Model
{
    protected $table = 'pms_outlets';

    protected $guarded = ['id'];

    public const TYPES = [
        'restaurant' => 'Restoran', 'bar' => 'Bar', 'room_service' => 'Oda servisi', 'minibar' => 'Minibar',
        'banquet' => 'Banket', 'spa' => 'Spa', 'shop' => 'Mağaza', 'other' => 'Diğer',
    ];

    public const SOURCES = ['simphony' => 'Simphony', 'rsone' => 'RS ONE', 'manual' => 'Elle (PMS)'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'simphony_rvc_number' => 'integer', 'sort' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function defaultTransactionCode(): BelongsTo
    {
        return $this->belongsTo(TransactionCode::class, 'default_transaction_code_id');
    }

    public function transactionCodes(): HasMany
    {
        return $this->hasMany(TransactionCode::class, 'outlet_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
