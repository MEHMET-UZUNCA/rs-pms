<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 6. aşama veritabanı: cari hesaplar, acente komisyonları, stopaj, Logo Tiger aktarımı (docs/03-veritabani.md H, L, N2, N3, P1).
 */
class StageSixSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_ar_account_types' => ['id', 'property_id', 'code', 'name', 'payment_terms_days', 'credit_limit_default'],
        'pms_ar_accounts' => ['id', 'property_id', 'ar_account_type_id', 'account_number', 'profile_id', 'name', 'status', 'restricted_reason',
            'credit_limit', 'payment_terms_days', 'currency_code', 'statement_email', 'logo_cari_code', 'contact_name', 'contact_phone', 'legacy_id'],
        'pms_ar_invoices' => ['id', 'property_id', 'ar_account_id', 'invoice_id', 'folio_id', 'business_date', 'document_date', 'due_date',
            'amount', 'open_amount', 'currency_code', 'status', 'reference', 'dispute_note', 'legacy_id'],
        'pms_ar_payments' => ['id', 'property_id', 'ar_account_id', 'business_date', 'payment_date', 'transaction_code_id', 'amount',
            'unallocated_amount', 'currency_code', 'exchange_rate', 'reference', 'cashier_shift_id', 'posted_by', 'reversal_of_id', 'created_at'],
        'pms_ar_allocations' => ['id', 'ar_payment_id', 'ar_invoice_id', 'amount', 'allocated_by', 'created_at', 'reversed_at', 'reversed_by'],
        'pms_ar_statements' => ['id', 'ar_account_id', 'statement_date', 'period_from', 'period_to', 'opening_balance', 'closing_balance', 'file_path', 'sent_at'],
        'pms_ar_reminders' => ['id', 'ar_account_id', 'level', 'sent_at', 'document_id'],
        'pms_tax_withholdings' => ['id', 'property_id', 'ar_account_id', 'ar_payment_id', 'invoice_id', 'tax_id', 'label', 'rate', 'amount',
            'withheld_on', 'certificate_number', 'status'],
        'pms_commission_codes' => ['id', 'property_id', 'code', 'name', 'currency_code'],
        'pms_commission_rules' => ['id', 'commission_code_id', 'basis', 'percent', 'amount_per_night', 'rate_code_id', 'valid_from', 'valid_to'],
        'pms_commissions' => ['id', 'property_id', 'reservation_id', 'agent_profile_id', 'commission_code_id', 'base_amount', 'commission_amount',
            'currency_code', 'status', 'paid_at', 'ar_payment_id'],
        'pms_logo_account_mappings' => ['id', 'property_id', 'mappable_type', 'mappable_id', 'logo_key', 'logo_account_code', 'logo_cost_center',
            'logo_department', 'logo_project_code', 'valid_from'],
        'pms_logo_export_batches' => ['id', 'property_id', 'business_date', 'type', 'status', 'logo_document_number', 'attempts', 'sent_at',
            'response', 'error', 'replaces_batch_id'],
        'pms_logo_export_lines' => ['id', 'batch_id', 'line_no', 'account_code', 'cost_center', 'debit', 'credit', 'currency_code',
            'foreign_amount', 'exchange_rate', 'description', 'source_type', 'source_id'],
    ];

    public function test_stage_six_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_ar_payment_allocated_to_invoice(): void
    {
        $f = $this->fixture();
        $invoice = DB::table('pms_ar_invoices')->insertGetId([
            'property_id' => $f['property'], 'ar_account_id' => $f['account'], 'business_date' => '2026-11-25', 'document_date' => '2026-11-25',
            'due_date' => '2026-12-25', 'amount' => 10000, 'open_amount' => 10000, 'currency_code' => 'TRY',
        ]);
        $payment = DB::table('pms_ar_payments')->insertGetId([
            'property_id' => $f['property'], 'ar_account_id' => $f['account'], 'business_date' => '2026-11-26', 'payment_date' => '2026-11-26',
            'transaction_code_id' => $f['code'], 'amount' => 10000, 'unallocated_amount' => 0, 'currency_code' => 'TRY',
        ]);
        DB::table('pms_ar_allocations')->insert(['ar_payment_id' => $payment, 'ar_invoice_id' => $invoice, 'amount' => 10000]);
        DB::table('pms_ar_invoices')->where('id', $invoice)->update(['open_amount' => 0, 'status' => 'paid']);

        $this->assertSame('paid', DB::table('pms_ar_invoices')->where('id', $invoice)->value('status'));
        $this->assertSame(10000.0, (float) DB::table('pms_ar_allocations')->where('ar_invoice_id', $invoice)->sum('amount'));
    }

    public function test_ar_payment_cannot_be_deleted(): void
    {
        $f = $this->fixture();
        $payment = DB::table('pms_ar_payments')->insertGetId([
            'property_id' => $f['property'], 'ar_account_id' => $f['account'], 'business_date' => '2026-11-26', 'payment_date' => '2026-11-26',
            'transaction_code_id' => $f['code'], 'amount' => 500, 'unallocated_amount' => 500, 'currency_code' => 'TRY',
        ]);
        $this->expectException(QueryException::class);
        DB::table('pms_ar_payments')->where('id', $payment)->delete();
    }

    public function test_logo_export_line_cannot_be_changed(): void
    {
        $f = $this->fixture();
        $batch = DB::table('pms_logo_export_batches')->insertGetId(['property_id' => $f['property'], 'business_date' => '2026-11-25', 'type' => 'daily_revenue']);
        DB::table('pms_logo_export_lines')->insert([
            ['batch_id' => $batch, 'line_no' => 1, 'account_code' => '120.01', 'debit' => 4000, 'credit' => 0, 'currency_code' => 'TRY', 'description' => 'Misafir alacakları'],
            ['batch_id' => $batch, 'line_no' => 2, 'account_code' => '600.01', 'debit' => 0, 'credit' => 4000, 'currency_code' => 'TRY', 'description' => 'Konaklama geliri'],
        ]);
        $this->assertSame(
            (float) DB::table('pms_logo_export_lines')->where('batch_id', $batch)->sum('debit'),
            (float) DB::table('pms_logo_export_lines')->where('batch_id', $batch)->sum('credit')
        );

        $this->expectException(QueryException::class);
        DB::table('pms_logo_export_lines')->where('batch_id', $batch)->where('line_no', 1)->update(['debit' => 1]);
    }

    public function test_company_profile_links_to_its_ar_account(): void
    {
        $f = $this->fixture();
        DB::table('pms_profiles')->where('id', $f['company'])->update(['ar_account_id' => $f['account']]);
        $this->assertSame($f['account'], (int) DB::table('pms_profiles')->where('id', $f['company'])->value('ar_account_id'));
    }

    private function fixture(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $p = DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
        $group = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'PAY', 'name' => 'Ödemeler', 'type' => 'payment']);
        $code = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $group, 'code' => '920', 'name' => 'Bank Payment', 'type' => 'payment', 'payment_kind' => 'bank_transfer']);
        $company = DB::table('pms_profiles')->insertGetId(['property_id' => $p, 'type' => 'travel_agent', 'company_name' => 'Örnek Acente', 'display_name' => 'Örnek Acente']);
        $account = DB::table('pms_ar_accounts')->insertGetId(['property_id' => $p, 'account_number' => 'AR-001', 'profile_id' => $company, 'name' => 'Örnek Acente', 'currency_code' => 'TRY', 'logo_cari_code' => '120.01.001']);

        return ['property' => $p, 'code' => $code, 'company' => $company, 'account' => $account];
    }
}
