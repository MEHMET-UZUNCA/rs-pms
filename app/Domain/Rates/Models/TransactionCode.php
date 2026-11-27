<?php

namespace App\Domain\Rates\Models;

use App\Domain\Core\Models\Tax;
use App\Domain\Pos\Models\Outlet;
use App\Support\Audit\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tablo: pms_transaction_codes — tasarım: docs/03-veritabani.md */
class TransactionCode extends Model
{
    use Audited;
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
            'disabled_in_prearrival' => 'boolean',
            'disabled_in_billing' => 'boolean',
            'disabled_in_ar' => 'boolean',
            'disabled_in_payments' => 'boolean',
            'direct_debit' => 'boolean',
            'non_hotel_revenue' => 'boolean',
            'open_cash_drawer' => 'boolean',
            'hostess_enabled' => 'boolean',
            'post_tax_only' => 'boolean',
            'default_price' => 'decimal:2',
            'cc_commission_pct' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TransactionCodeGroup::class, 'group_id');
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'pms_transaction_code_taxes', 'transaction_code_id', 'tax_id')
            ->withPivot(['id', 'sequence', 'calc_base', 'valid_from', 'valid_to']);
    }

    /** Fidelio "Adjustment Dept": bu kodun düzeltme/indirim kodu (ör. 100 Room → 102 DISC Room). */
    public function adjustmentCode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'adjustment_transaction_code_id');
    }

    /** İndirim/rebate kodunun ait olduğu asıl kod (ör. 103 REBATE Room → 100 Room). */
    public function discountOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'discount_of_transaction_code_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
