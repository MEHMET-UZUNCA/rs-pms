<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 4. aşama veritabanı: kasa (docs/03-veritabani.md G, N1, N4, P1) ve mali kayıt kuralının veritabanında zorlanması.
 */
class StageFourSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pms_window_types' => ['id', 'property_id', 'code', 'name'],
        'pms_folio_groups' => ['id', 'property_id', 'code', 'name'],
        'pms_article_groups' => ['id', 'property_id', 'code', 'name'],
        'pms_cashier_terminals' => ['id', 'property_id', 'code', 'name', 'outlet_id', 'default_float', 'printer_name'],
        'pms_cashiers' => ['id', 'property_id', 'number', 'initials', 'name', 'user_id', 'is_interface', 'pin_hash', 'pin_attempts',
            'locked_until', 'max_cash_refund', 'allowed_payment_codes', 'legacy_id'],
        'pms_cashier_shifts' => ['id', 'property_id', 'cashier_id', 'cashier_terminal_id', 'business_date', 'status', 'opening_float',
            'opened_at', 'opened_by', 'closed_at', 'closed_by'],
        'pms_cashier_shift_totals' => ['id', 'shift_id', 'transaction_code_id', 'currency_code', 'opening_amount', 'system_amount',
            'counted_amount', 'difference'],
        'pms_folios' => ['id', 'property_id', 'folio_number', 'type', 'reservation_id', 'window', 'name', 'folio_group_id', 'window_type_id',
            'valid_from', 'valid_until', 'actual_from', 'actual_until', 'payee_profile_id', 'ar_account_id', 'event_booking_id', 'status',
            'balance', 'opened_business_date', 'closed_business_date', 'closed_by', 'legacy_id'],
        'pms_routing_rules' => ['id', 'property_id', 'source_reservation_id', 'target_type', 'target_window', 'target_reservation_id',
            'target_folio_id', 'transaction_code_id', 'transaction_code_group_id', 'date_from', 'date_to', 'credit_limit'],
        'pms_invoices' => ['id', 'property_id', 'invoice_number', 'series', 'type', 'status', 'folio_id', 'reservation_id',
            'bill_to_profile_id', 'bill_to_name', 'bill_to_tax_office', 'bill_to_tax_number', 'bill_to_address', 'business_date',
            'issued_at', 'issued_by', 'currency_code', 'total_net', 'total_tax', 'total_gross', 'total_paid', 'credit_note_for_id',
            'replaces_invoice_id', 'cancelled_at', 'cancelled_by', 'cancel_reason_code_id', 'print_count', 'pdf_path', 'e_document_uuid'],
        'pms_postings' => ['id', 'property_id', 'business_date', 'folio_id', 'reservation_id', 'room_id', 'transaction_code_id', 'ledger',
            'kind', 'description', 'reference', 'quantity', 'unit_price', 'gross_unit_price', 'amount', 'net_amount', 'tax_amount',
            'foreign_currency', 'foreign_unit_price', 'foreign_amount', 'exchange_rate', 'stay_date', 'reservation_night_id', 'package_id',
            'outlet_id', 'event_id', 'pos_posting_id', 'pos_check_number', 'market_segment_id', 'origin_reservation_id', 'origin_folio_id',
            'origin_profile_id', 'discount_of_id', 'rebate_code_id', 'source', 'cashier_shift_id', 'posted_by', 'reversal_of_id',
            'reversed_by_id', 'transfer_from_id', 'reason_code_id', 'note', 'invoice_id', 'idempotency_key', 'legacy_id',
            'legacy_ledger_status', 'created_at'],
        'pms_posting_taxes' => ['id', 'posting_id', 'tax_id', 'rate', 'base_amount', 'tax_amount'],
        'pms_invoice_lines' => ['id', 'invoice_id', 'posting_id', 'transaction_code_id', 'description', 'stay_date', 'quantity',
            'unit_price', 'net_amount', 'tax_amount', 'gross_amount', 'tax_breakdown'],
        'pms_tax_exemptions' => ['id', 'property_id', 'code', 'name', 'tax_id', 'exemptable_type', 'exemptable_id', 'valid_from', 'valid_to', 'document_no'],
        'pms_billing_authorizations' => ['id', 'reservation_id', 'type', 'payment_token_id', 'ar_account_id', 'amount', 'currency_code',
            'approval_code', 'status', 'approved_by', 'approved_at', 'expires_at'],
        'pms_approval_requests' => ['id', 'property_id', 'type', 'approvable_type', 'approvable_id', 'amount', 'currency_code',
            'reason_code_id', 'reason', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_note'],
        'pms_cash_transfers' => ['id', 'property_id', 'business_date', 'kind', 'from_shift_id', 'to_shift_id', 'currency_code', 'amount',
            'status', 'requested_by', 'received_by', 'received_at'],
        'pms_currency_exchanges' => ['id', 'property_id', 'business_date', 'cashier_shift_id', 'profile_id', 'folio_id', 'direction',
            'currency_code', 'foreign_amount', 'exchange_rate', 'local_amount', 'fee_amount', 'document_no'],
        'pms_security_deposits' => ['id', 'property_id', 'reservation_id', 'profile_id', 'method', 'required_amount', 'collected_amount',
            'withheld_amount', 'refunded_amount', 'currency_code', 'status', 'evidence_path', 'refund_reference'],
        'pms_payment_links' => ['id', 'property_id', 'folio_id', 'reservation_id', 'provider', 'amount', 'currency_code', 'url',
            'external_id', 'status', 'paid_posting_id', 'expires_at'],
        'pms_articles' => ['id', 'property_id', 'article_group_id', 'transaction_code_id', 'code', 'name', 'price', 'barcode'],
        'pms_document_templates' => ['id', 'property_id', 'type', 'language_code', 'name', 'body', 'is_default'],
        'pms_documents' => ['id', 'property_id', 'documentable_type', 'documentable_id', 'template_id', 'type', 'number', 'file_path', 'sent_to', 'channel'],
        'pms_voucher_types' => ['id', 'property_id', 'code', 'name', 'barcode_prefix', 'transaction_code_id'],
        'pms_vouchers' => ['id', 'property_id', 'voucher_type_id', 'number', 'amount', 'currency_code', 'issued_to_profile_id',
            'issued_at', 'valid_until', 'status', 'used_posting_id', 'rsone_gift_voucher_id'],
    ];

    public function test_stage_four_tables_and_columns_exist(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tablo yok: {$table}");
            $missing = array_values(array_diff($columns, Schema::getColumnListing($table)));
            $this->assertSame([], $missing, "{$table} tablosunda eksik kolon: ".implode(', ', $missing));
        }
    }

    public function test_posting_cannot_be_deleted(): void
    {
        $f = $this->fixture();
        $this->expectException(QueryException::class);
        DB::table('pms_postings')->where('id', $f['posting'])->delete();
    }

    public function test_posting_amount_cannot_be_changed(): void
    {
        $f = $this->fixture();
        $this->expectException(QueryException::class);
        DB::table('pms_postings')->where('id', $f['posting'])->update(['amount' => 1]);
    }

    public function test_posting_is_corrected_with_a_reversal(): void
    {
        $f = $this->fixture();
        $reversal = DB::table('pms_postings')->insertGetId([
            'property_id' => $f['property'], 'business_date' => '2026-11-25', 'folio_id' => $f['folio'], 'transaction_code_id' => $f['code'],
            'kind' => 'adjustment', 'description' => 'Ters kayıt', 'amount' => -4000, 'reversal_of_id' => $f['posting'],
        ]);
        DB::table('pms_postings')->where('id', $f['posting'])->update(['reversed_by_id' => $reversal]);

        $this->assertSame($reversal, (int) DB::table('pms_postings')->where('id', $f['posting'])->value('reversed_by_id'));
        $this->assertSame(0.0, (float) DB::table('pms_postings')->where('folio_id', $f['folio'])->sum('amount'));
    }

    public function test_invoice_cannot_be_deleted_but_can_be_cancelled(): void
    {
        $f = $this->fixture();
        $invoice = DB::table('pms_invoices')->insertGetId([
            'property_id' => $f['property'], 'invoice_number' => 'A-000001', 'folio_id' => $f['folio'], 'bill_to_name' => 'Örnek Misafir',
            'business_date' => '2026-11-25', 'currency_code' => 'TRY', 'total_gross' => 4000,
        ]);
        DB::table('pms_invoices')->where('id', $invoice)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->assertSame('cancelled', DB::table('pms_invoices')->where('id', $invoice)->value('status'));

        $this->expectException(QueryException::class);
        DB::table('pms_invoices')->where('id', $invoice)->delete();
    }

    public function test_reservation_has_at_most_one_folio_per_window(): void
    {
        $f = $this->fixture();
        $this->expectException(QueryException::class);
        DB::table('pms_folios')->insert(['property_id' => $f['property'], 'folio_number' => 'F-2', 'reservation_id' => $f['reservation'],
            'window' => 1, 'opened_business_date' => '2026-11-25']);
    }

    private function fixture(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus']);
        $p = DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
        $type = DB::table('pms_room_types')->insertGetId(['property_id' => $p, 'code' => 'DBL', 'name' => 'Double']);
        $group = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'ROOM', 'name' => 'Konaklama']);
        $code = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $group, 'code' => '100', 'name' => 'Room']);
        $rate = DB::table('pms_rate_codes')->insertGetId(['property_id' => $p, 'code' => 'BAR', 'name' => 'Raf', 'currency_code' => 'TRY', 'room_transaction_code_id' => $code]);
        $segment = DB::table('pms_market_segments')->insertGetId(['property_id' => $p, 'code' => 'IND', 'name' => 'Bireysel']);
        $source = DB::table('pms_source_codes')->insertGetId(['property_id' => $p, 'code' => 'DIR', 'name' => 'Doğrudan']);
        $resType = DB::table('pms_reservation_types')->insertGetId(['property_id' => $p, 'code' => 'GTD', 'name' => 'Garantili']);
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $p, 'type' => 'individual', 'display_name' => 'Örnek, Misafir']);
        $res = DB::table('pms_reservations')->insertGetId([
            'property_id' => $p, 'confirmation_number' => 'R-1', 'reservation_type_id' => $resType, 'profile_id' => $profile,
            'arrival_date' => '2026-11-25', 'departure_date' => '2026-11-26', 'original_departure_date' => '2026-11-26', 'nights' => 1,
            'room_type_id' => $type, 'charge_room_type_id' => $type, 'rate_code_id' => $rate, 'currency_code' => 'TRY',
            'market_segment_id' => $segment, 'source_code_id' => $source,
        ]);
        $folio = DB::table('pms_folios')->insertGetId(['property_id' => $p, 'folio_number' => 'F-1', 'reservation_id' => $res, 'window' => 1, 'opened_business_date' => '2026-11-25']);
        $posting = DB::table('pms_postings')->insertGetId([
            'property_id' => $p, 'business_date' => '2026-11-25', 'folio_id' => $folio, 'reservation_id' => $res, 'transaction_code_id' => $code,
            'kind' => 'charge', 'description' => 'Konaklama', 'amount' => 4000, 'source' => 'night_audit',
        ]);

        return ['property' => $p, 'folio' => $folio, 'reservation' => $res, 'code' => $code, 'posting' => $posting];
    }
}
