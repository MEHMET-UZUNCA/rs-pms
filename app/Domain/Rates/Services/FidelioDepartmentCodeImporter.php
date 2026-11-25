<?php

namespace App\Domain\Rates\Services;

use Illuminate\Support\Facades\DB;

/**
 * Fidelio "Department Codes" listesini (No; Code; Description; Manual; Group) RS PMS işlem kodlarına aktarır.
 *  - Kod numaraları ve adlar aynen alınır; "Code" kısaltması (ROO, DIS, REB, PEN, IND …) kategori olarak saklanır.
 *  - Grup numaraları (10, 11, 15 …) işlem kodu grubu olur; grup adları ayrıca "Department Code Groups" listesinden güncellenebilir.
 *  - TAX grubundaki vergi departmanları (TX0–TX5) işlem kodu olmaz; açıklaması eşleşen vergiye departman numarası olarak yazılır.
 *  - İndirim/rebate kodları kategoriden (DIS, REB) ya da addan ("DISC …", "… Disc.", "… Reb.") tanınır ve asıl koda bağlanır
 *    (bkz. linkDiscounts).
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
                    'manual_post_allowed' => $row['manual'], 'is_discount' => self::isDiscount($category, $row['description']), 'updated_at' => now(),
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
            str_contains($d, 'breakfast') || str_contains($d, 'break.') => 'breakfast',
            str_contains($d, 'lunch') => 'lunch',
            str_contains($d, 'dinner') => 'dinner',
            str_contains($d, 'minibar') => 'minibar',
            (bool) preg_match('/\b(beverage|bvrg|bvr|bev|bevrage)\b/', $d) => 'beverage',
            str_contains($d, 'food') => 'food',
            str_contains($d, 'laundry') => 'laundry',
            (bool) preg_match('/\b(telephone|fax)\b/', $d) => 'telephone',
            (bool) preg_match('/sauna|massage|fitness|spa\b/', $d) => 'spa',
            $category === 'ROO' || (bool) preg_match('/^(\S+\s+)?room\b(?!\s+serv)/', $d) => 'room',
            default => 'other',
        };
    }

    /** İndirim / rebate kodu mu: kategori DIS/REB ya da adı "DISC …", "REB. …", "… Disc.", "… Rebate" biçiminde. */
    public static function isDiscount(string $category, string $description): bool
    {
        return in_array(strtoupper(trim($category)), ['DIS', 'REB'], true) || self::baseName($description) !== self::clean($description);
    }

    /** İndirim/rebate önek ve sonekleri atılmış ad (ör. "DISC.Others La Habenera" → "Others La Habenera"). */
    public static function baseName(string $description): string
    {
        $s = self::clean($description);
        $s = preg_replace('/^(DISCOUNT|DISC|DIS|D\.\s?COUNT|REBATE|REBETA|REB)(\.\s*|\s+)/i', '', $s);
        $s = preg_replace('/\s+(DISCOUNT|DISC|DIS|REBATE|REB)\.?$/i', '', $s);

        return trim($s);
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Karşılaştırma için sırasız kelime kümesi; yazım farkları (BVR/BEVRAGE → BVRG, Ind.Pension → IND PENSION) giderilir. */
    private static function tokens(string $s): string
    {
        $words = preg_split('/[\s.\-]+/', mb_strtoupper($s), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(fn ($w) => match ($w) {
            'BVR', 'BEVERAGE', 'BEVRAGE', 'BEV' => 'BVRG', 'OTHERS' => 'OTHER', 'BREAK' => 'BREAKFAST', 'SER', 'SERVIS' => 'SERVICE', default => $w,
        }, $words);
        sort($words);

        return implode(' ', $words);
    }

    /**
     * İndirim/rebate kodunu asıl koda bağlar. Sırasıyla: önek/sonek atılmış adın kelime kümesi eşleşen kod; aynı gruptaki en
     * benzer adlı kod (benzerlik %60 ve üstü); numarası kendisinden küçük en yakın indirim olmayan kod.
     */
    private function linkDiscounts(int $propertyId): int
    {
        $codes = DB::table('pms_transaction_codes')->where('property_id', $propertyId)
            ->get(['id', 'code', 'name', 'group_id', 'is_discount', 'discount_of_transaction_code_id']);
        $bases = $codes->where('is_discount', false)->filter(fn ($c) => is_numeric($c->code));
        $byTokens = $bases->keyBy(fn ($c) => self::tokens($c->name));
        $linked = 0;

        foreach ($codes->where('is_discount', true)->whereNull('discount_of_transaction_code_id') as $code) {
            $base = self::baseName($code->name);
            $target = $byTokens->get(self::tokens($base));
            if (! $target) {
                $best = null;
                $bestScore = 0.0;
                foreach ($bases->where('group_id', $code->group_id) as $candidate) {
                    similar_text(self::tokens($base), self::tokens($candidate->name), $score);
                    if ($score > $bestScore) {
                        [$best, $bestScore] = [$candidate, $score];
                    }
                }
                $target = $bestScore >= 60 ? $best : null;
            }
            $target ??= $bases->filter(fn ($c) => (int) $c->code < (int) $code->code)->sortByDesc(fn ($c) => (int) $c->code)->first();
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
