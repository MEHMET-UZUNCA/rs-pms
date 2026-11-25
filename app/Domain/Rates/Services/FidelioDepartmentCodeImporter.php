<?php

namespace App\Domain\Rates\Services;

use Illuminate\Support\Facades\DB;

/**
 * Fidelio "Department Codes" listesini (No; Code; Description; Manual; Group) RS PMS işlem kodlarına aktarır.
 *  - Kod numaraları ve adlar aynen alınır; "Code" kısaltması (ROO, DIS, REB, PEN, IND …) kategori olarak saklanır.
 *  - Grup numaraları (10, 11, 15 …) işlem kodu grubu olur; grup adları ayrıca "Department Code Groups" listesinden güncellenebilir.
 *  - TAX grubundaki vergi departmanları (TX0–TX5) işlem kodu olmaz; açıklaması eşleşen vergiye departman numarası olarak yazılır.
 *  - İndirim (DIS) ve rebate (REB) kodları, adlarındaki önek atılınca adı eşleşen asıl koda bağlanır; bulunamazsa
 *    numarası kendisinden küçük en yakın indirim olmayan koda bağlanır.
 *  - Var olan kodun tipi, ödeme türü ve outlet bağlantısı korunur (yalnız ad, kategori, grup, elle girilebilirlik güncellenir).
 *  - 900 ve üstü kodlar yeni açılıyorsa ödeme kodu sayılır (Fidelio'daki numaralandırma).
 */
class FidelioDepartmentCodeImporter
{
    /**
     * @param  array<int, array{no:string, code:string, description:string, manual:bool, group:string}>  $rows
     * @return array{created:int, updated:int, tax_departments:int, discount_links:int}
     */
    public function import(int $propertyId, array $rows): array
    {
        return DB::transaction(function () use ($propertyId, $rows) {
            $stats = ['created' => 0, 'updated' => 0, 'tax_departments' => 0, 'discount_links' => 0];

            foreach ($rows as $row) {
                if (strtoupper($row['group']) === 'TAX' || str_starts_with(strtoupper($row['code']), 'TX')) {
                    $updated = DB::table('pms_taxes')->where('property_id', $propertyId)
                        ->whereRaw('UPPER(REPLACE(legacy_department, \' \', \'\')) = ?', [strtoupper(str_replace(' ', '', $row['description']))])
                        ->update(['legacy_department_number' => (int) $row['no']]);
                    $stats['tax_departments'] += $updated > 0 ? 1 : 0;

                    continue;
                }

                $groupId = $this->group($propertyId, $row['group'], (int) $row['no']);
                $existing = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', $row['no'])->first();
                $category = strtoupper(trim($row['code']));
                $values = [
                    'name' => trim($row['description']), 'category' => $category, 'group_id' => $groupId,
                    'manual_post_allowed' => $row['manual'], 'is_discount' => in_array($category, ['DIS', 'REB'], true), 'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('pms_transaction_codes')->where('id', $existing->id)->update($values);
                    $stats['updated']++;
                } else {
                    $isPayment = (int) $row['no'] >= 900;
                    DB::table('pms_transaction_codes')->insert(array_merge($values, [
                        'property_id' => $propertyId, 'code' => $row['no'], 'type' => $isPayment ? 'payment' : 'revenue',
                        'revenue_bucket' => $isPayment ? null : $this->bucket($category, $row['description']), 'is_active' => true, 'created_at' => now(),
                    ]));
                    $stats['created']++;
                }
            }

            $stats['discount_links'] = $this->linkDiscounts($propertyId);

            return $stats;
        });
    }

    private function group(int $propertyId, string $number, int $codeNo): int
    {
        $number = trim($number) !== '' ? trim($number) : ($codeNo >= 900 ? 'PAY' : 'GEN');
        $id = DB::table('pms_transaction_code_groups')->where('property_id', $propertyId)->where('code', $number)->value('id');
        if ($id) {
            return (int) $id;
        }

        return DB::table('pms_transaction_code_groups')->insertGetId([
            'property_id' => $propertyId, 'code' => $number, 'name' => 'Fidelio grup '.$number, 'type' => $codeNo >= 900 ? 'payment' : 'revenue',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function bucket(string $category, string $description): string
    {
        $d = strtolower($description);

        return match (true) {
            str_contains($d, 'breakfast') => 'breakfast',
            str_contains($d, 'lunch') => 'lunch',
            str_contains($d, 'dinner') => 'dinner',
            in_array($category, ['ROO'], true) || str_contains($d, 'room') => 'room',
            str_contains($d, 'beverage') || str_contains($d, 'bvrg') => 'beverage',
            str_contains($d, 'food') => 'food',
            str_contains($d, 'minibar') => 'minibar',
            str_contains($d, 'laundry') => 'laundry',
            default => 'other',
        };
    }

    private function linkDiscounts(int $propertyId): int
    {
        $codes = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->get(['id', 'code', 'name', 'is_discount', 'discount_of_transaction_code_id']);
        $normalize = fn (string $s) => preg_replace('/\s+/', ' ', strtoupper(trim($s)));
        $byName = $codes->where('is_discount', false)->keyBy(fn ($c) => $normalize($c->name));
        $linked = 0;

        foreach ($codes->where('is_discount', true)->whereNull('discount_of_transaction_code_id') as $code) {
            $base = preg_replace('/^(DISC\.?|DIS\.?|REBATE|REB\.?)\s+/i', '', trim($code->name));
            $target = $byName->get($normalize($base))
                ?? $codes->where('is_discount', false)->filter(fn ($c) => is_numeric($c->code) && (int) $c->code < (int) $code->code)->sortByDesc(fn ($c) => (int) $c->code)->first();
            if ($target) {
                DB::table('pms_transaction_codes')->where('id', $code->id)->update(['discount_of_transaction_code_id' => $target->id]);
                $linked++;
            }
        }

        return $linked;
    }

    /** Fidelio'dan dışa aktarılan / kopyalanan liste: sekme, noktalı virgül ya da virgülle ayrılmış; başlık satırı atlanır. */
    public static function parse(string $content): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $cols = array_map('trim', preg_split('/\t|;/', $line));
            if (count($cols) < 3 || ! is_numeric($cols[0])) {
                continue;
            }
            $manual = strtolower($cols[3] ?? '');
            $rows[] = ['no' => $cols[0], 'code' => $cols[1], 'description' => $cols[2],
                'manual' => in_array($manual, ['1', 'x', 'true', 'yes', 'evet', '✓', '√'], true), 'group' => $cols[4] ?? ''];
        }

        return $rows;
    }
}
