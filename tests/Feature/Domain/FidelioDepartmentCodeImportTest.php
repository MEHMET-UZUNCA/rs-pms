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
}
