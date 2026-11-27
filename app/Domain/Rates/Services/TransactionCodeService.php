<?php

namespace App\Domain\Rates\Services;

use App\Domain\Rates\Models\TransactionCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Departman kodu (Fidelio "Department Codes") kaydetme, tarihli vergi bağlantıları, silme ve sıralama.
 *  - Bağlantılı kodlar (Adjustment Dept, Return Dep., CC Commission, indirimin asıl kodu) aynı tesisten olmalı ve kodun kendisi olamaz.
 *  - Vergi satırları: aynı vergi aynı kodda çakışan tarihlerle iki kez olamaz; bitiş başlangıçtan önce olamaz.
 *  - Kayıtlarda, fiyat kodlarında, paketlerde, outlet'lerde ya da başka kodlarda kullanılan kod silinemez; pasife alınır.
 */
class TransactionCodeService
{
    private const USED_IN = [
        ['pms_postings', 'transaction_code_id', 'folyo kayıtları'],
        ['pms_rate_codes', 'room_transaction_code_id', 'fiyat kodları'],
        ['pms_package_elements', 'transaction_code_id', 'paket elemanları'],
        ['pms_outlets', 'default_transaction_code_id', 'outlet varsayılan kodu'],
        ['pms_pos_revenue_mappings', 'transaction_code_id', 'Simphony gelir eşlemeleri'],
        ['pms_transaction_codes', 'adjustment_transaction_code_id', 'başka kodun düzeltme kodu'],
        ['pms_transaction_codes', 'discount_of_transaction_code_id', 'indirim kodları'],
        ['pms_transaction_codes', 'return_transaction_code_id', 'başka kodun iade kodu'],
        ['pms_transaction_codes', 'cc_commission_transaction_code_id', 'kart komisyonu'],
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{tax_id:int|string, valid_from:?string, valid_to:?string}>  $taxes
     */
    public function save(int $propertyId, array $data, array $taxes, ?TransactionCode $code = null): TransactionCode
    {
        return DB::transaction(function () use ($propertyId, $data, $taxes, $code) {
            if (! DB::table('pms_transaction_code_groups')->where('property_id', $propertyId)->where('id', $data['group_id'])->exists()) {
                throw new TransactionCodeException(__('pms.err.group_property'));
            }
            foreach (['adjustment_transaction_code_id' => 'Düzeltme kodu', 'return_transaction_code_id' => 'İade kodu',
                'cc_commission_transaction_code_id' => 'Kart komisyonu kodu', 'discount_of_transaction_code_id' => 'Asıl kod'] as $field => $label) {
                $id = $data[$field] ?? null;
                if (! $id) {
                    continue;
                }
                if ($code && (int) $id === $code->id) {
                    throw new TransactionCodeException(__('pms.err.code_self', ['label' => $label]));
                }
                if (! DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('id', $id)->whereNull('deleted_at')->exists()) {
                    throw new TransactionCodeException(__('pms.err.code_missing', ['label' => $label]));
                }
            }
            if (empty($data['is_discount'])) {
                $data['discount_of_transaction_code_id'] = null;
            }

            $rows = $this->taxRows($propertyId, $taxes);

            $code ??= new TransactionCode(['property_id' => $propertyId,
                'sort' => (int) DB::table('pms_transaction_codes')->where('property_id', $propertyId)->max('sort') + 1]);
            $code->fill($data)->save();

            DB::table('pms_transaction_code_taxes')->where('transaction_code_id', $code->id)->delete();
            foreach ($rows as $i => $row) {
                DB::table('pms_transaction_code_taxes')->insert($row + ['transaction_code_id' => $code->id, 'sequence' => $i + 1, 'calc_base' => 'net']);
            }

            return $code->refresh();
        });
    }

    /** @return array<int, array{tax_id:int, valid_from:?string, valid_to:?string}> */
    private function taxRows(int $propertyId, array $taxes): array
    {
        $rows = [];
        foreach ($taxes as $tax) {
            if (empty($tax['tax_id'])) {
                continue;
            }
            $row = ['tax_id' => (int) $tax['tax_id'], 'valid_from' => $tax['valid_from'] ?: null, 'valid_to' => $tax['valid_to'] ?: null];
            if (! DB::table('pms_taxes')->where('property_id', $propertyId)->where('id', $row['tax_id'])->exists()) {
                throw new TransactionCodeException(__('pms.err.tax_property'));
            }
            if ($row['valid_from'] && $row['valid_to'] && $row['valid_to'] < $row['valid_from']) {
                throw new TransactionCodeException(__('pms.err.tax_line_dates'));
            }
            foreach ($rows as $other) {
                $overlap = ($other['valid_to'] === null || $row['valid_from'] === null || $row['valid_from'] <= $other['valid_to'])
                    && ($row['valid_to'] === null || $other['valid_from'] === null || $other['valid_from'] <= $row['valid_to']);
                if ($other['tax_id'] === $row['tax_id'] && $overlap) {
                    throw new TransactionCodeException(__('pms.err.tax_overlap'));
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function delete(TransactionCode $code): void
    {
        $usage = [];
        foreach (self::USED_IN as [$table, $column, $label]) {
            if (DB::table($table)->where($column, $code->id)->exists()) {
                $usage[] = $label;
            }
        }
        if ($usage !== []) {
            throw new TransactionCodeException(__('pms.err.code_in_use', ['code' => $code->code.' '.$code->name, 'usage' => implode(', ', array_unique($usage))]));
        }

        try {
            DB::transaction(function () use ($code) {
                DB::table('pms_transaction_code_taxes')->where('transaction_code_id', $code->id)->delete();
                $code->forceDelete();
            });
        } catch (QueryException) {
            throw new TransactionCodeException(__('pms.err.code_referenced', ['code' => $code->code.' '.$code->name]));
        }
    }

    /** Fidelio Up / Down: listedeki sırayı komşu kodla değiştirir. */
    public function move(TransactionCode $code, string $direction): void
    {
        DB::transaction(function () use ($code, $direction) {
            $ordered = TransactionCode::where('property_id', $code->property_id)->orderBy('sort')->orderByRaw('CAST(code AS UNSIGNED)')
                ->lockForUpdate()->get(['id', 'sort'])->values();
            $index = $ordered->search(fn ($c) => $c->id === $code->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= $ordered->count()) {
                return;
            }
            $list = $ordered->all();
            [$list[$index], $list[$swap]] = [$list[$swap], $list[$index]];
            foreach ($list as $i => $c) {
                if ((int) $c->sort !== $i + 1) {
                    DB::table('pms_transaction_codes')->where('id', $c->id)->update(['sort' => $i + 1]);
                }
            }
        });
    }
}
