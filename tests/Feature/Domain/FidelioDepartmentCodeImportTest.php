<?php

namespace Tests\Feature\Domain;

use App\Domain\Rates\Services\FidelioDepartmentCodeImporter;
use Illuminate\Support\Facades\DB;

class FidelioDepartmentCodeImportTest extends DomainTestCase
{
    /** Fidelio Department Codes ekranından (Rocks Hotel) satırlar */
    private const SAMPLE = "No\tCode\tDescription\tManual\tGroup\n"
        ."0\tTX0\tVAT 0 %\t\tTAX\n1\tTX1\tVAT 5 %\t\tTAX\n2\tTX2\tVAT 10 %\t\tTAX\n"
        ."100\tROO\tRoom\t✓\t10\n102\tDIS\tDISC Room\t✓\t10\n103\tREB\tREBATE Room\t✓\t10\n"
        ."110\tPEN\tPension Breakfast\t✓\t15\n112\tREB\tREBATE Pension Breakfast\t✓\t15\n"
        ."116\tDIS\tDIS. Casino Room No-Show\t✓\t10\n120\tPEN\tPension Lunch\t✓\t15\n121\tDIS\tDISC Pension Lunch\t✓\t15\n"
        ."210\tIND\tInd. Pension Breakfast\t✓\t16\n211\tDIS\tDISC Ind. Pension Break.\t✓\t16\n";

    public function test_department_codes_are_imported_with_category_group_and_discount_links(): void
    {
        $result = (new FidelioDepartmentCodeImporter)->import($this->property, FidelioDepartmentCodeImporter::parse(self::SAMPLE));

        $this->assertSame(3, $result['tax_departments']);
        $this->assertSame(1, (int) DB::table('pms_taxes')->where('code', 'VAT5')->value('legacy_department_number'));
        $this->assertNull(DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '0')->first()); // vergi departmanı kod olmaz

        $code = fn (string $no) => DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', $no)->first();
        $this->assertSame('ROO', $code('100')->category);
        $this->assertSame('10', DB::table('pms_transaction_code_groups')->where('id', $code('100')->group_id)->value('code'));
        $this->assertSame('REB', $code('103')->category);
        $this->assertSame($code('100')->id, $code('103')->discount_of_transaction_code_id);
        $this->assertSame($code('110')->id, $code('112')->discount_of_transaction_code_id);
        $this->assertSame($code('115')->id, $code('116')->discount_of_transaction_code_id);   // "DIS. Casino Room No-Show" → 115
        $this->assertSame('lunch', $code('120')->revenue_bucket);
        $this->assertSame($code('210')->id, $code('211')->discount_of_transaction_code_id);  // ad eşleşmezse en yakın küçük kod
        $this->assertSame('cash', $code('900')->payment_kind);                                // var olan kodun ödeme türü korunur
    }

    public function test_full_rocks_department_code_list_is_imported_and_discounts_link_to_the_right_codes(): void
    {
        $rows = FidelioDepartmentCodeImporter::parse(file_get_contents(database_path('data/fidelio/department-codes.tsv')));
        $this->assertCount(277, $rows);

        $result = (new FidelioDepartmentCodeImporter)->import($this->property, $rows);
        $this->assertSame(6, $result['tax_departments']);

        $code = fn (string $no) => DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', $no)->first();
        $linksTo = fn (string $discount, string $base) => $this->assertSame($code($base)->id, $code($discount)->discount_of_transaction_code_id, "$discount → $base");

        $linksTo('387', '385');   // "DISC L HIRONDELLE FOOD" → "FOOD L HIRONDELLE" (sırasız kelime eşleşmesi)
        $linksTo('388', '386');   // "… BEVRAGE" yazım farkı
        $linksTo('399', '398');   // "DISC.Others La Habenera" (boşluksuz önek)
        $linksTo('411', '410');   // "D.COUNT DRAGON REST"
        $linksTo('441', '440');   // "… Disc." soneki
        $linksTo('543', '386');   // "REB BVRG Hirondella" → aynı grupta en benzer ad
        $linksTo('528', '526');   // "REBETA" yazım hatası
        $linksTo('672', '670');   // "Casino Food Rebate"
        $linksTo('119', '117');   // "Lyra Event Disc."
        $linksTo('325', '321');   // "REBATE BVRG  Cafe"

        $this->assertFalse((bool) $code('560')->is_discount);            // "VAT Discount %3" indirim kodu değil
        $this->assertSame('other', $code('346')->revenue_bucket);        // "Other Room Service" oda geliri değil
        $this->assertSame('beverage', $code('500')->revenue_bucket);     // "Banquet Bev. La Habenera"
        $this->assertSame('telephone', $code('405')->revenue_bucket);
        $this->assertSame('payment', $code('966')->type);
        $this->assertSame('95', DB::table('pms_transaction_code_groups')->where('id', $code('966')->group_id)->value('code'));
        $this->assertFalse((bool) $code('957')->manual_post_allowed);
    }

    public function test_seeded_codes_carry_rocks_tax_rules(): void
    {
        $taxOf = fn (string $no) => DB::table('pms_transaction_code_taxes as l')->join('pms_taxes as t', 't.id', '=', 'l.tax_id')
            ->where('l.transaction_code_id', $this->code($no))->pluck('t.code')->all();

        $this->assertSame(['VAT5'], $taxOf('100'));     // Fidelio: 100 Room → VAT 5 %
        $this->assertSame(['VAT5'], $taxOf('107'));     // Fidelio: 107 Room Supplement → VAT 5 %
        $this->assertSame(['VAT5'], $taxOf('320'));
        $this->assertSame(['VAT5'], $taxOf('513'));     // spa — teyit edilecek
        $this->assertSame(['VAT0'], $taxOf('580'));     // "Others %0"
        $this->assertSame(['VAT16'], $taxOf('553'));    // "DISC Others %16"
        $this->assertSame(['VAT20'], $taxOf('555'));    // "Others %20"
        $this->assertSame([], $taxOf('550'));           // kur farkı
        $this->assertSame([], $taxOf('900'));           // ödeme
        $this->assertSame([], $taxOf('800'));           // paid-out
        $this->assertSame(0, DB::table('pms_transaction_codes as tc')->where('tc.property_id', $this->property)->where('tc.type', 'revenue')
            ->whereNotIn('tc.code', ['550', '551', '880'])
            ->whereNotExists(fn ($q) => $q->from('pms_transaction_code_taxes as l')->whereColumn('l.transaction_code_id', 'tc.id'))->count());
    }
}
