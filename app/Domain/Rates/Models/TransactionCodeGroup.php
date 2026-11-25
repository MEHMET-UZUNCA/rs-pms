<?php

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Model;

/** Tablo: pms_transaction_code_groups — tasarım: docs/03-veritabani.md */
class TransactionCodeGroup extends Model
{
    protected $table = 'pms_transaction_code_groups';

    protected $guarded = ['id'];
}
