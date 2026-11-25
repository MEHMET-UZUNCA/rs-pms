<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fidelio uyumlu PROISV view'ları: aynı ad, aynı kolonlar ve sıra, Fidelio kod değerleri.
 */
class ProisvViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_view_has_exactly_the_fidelio_columns_in_order(): void
    {
        $expected = require base_path('tests/Fixtures/proisv_columns.php');
        $this->assertCount(13, $expected);
        $total = 0;
        foreach ($expected as $view => $columns) {
            $actual = $this->columns($view);
            $this->assertSame($columns, $actual, "{$view} kolonları Fidelio ile aynı değil");
            $total += count($actual);
        }
        $this->assertSame(391, $total);
    }

    public function test_reservation_and_posting_views_return_fidelio_codes(): void
    {
        $f = $this->fixture();

        $res = DB::table('pro_isv_reservationinfo')->where('DETAIL_DATE', '2026-11-25')->first();
        $this->assertSame(1, (int) $res->RESERVATIONSTATUS);      // Definite
        $this->assertSame(2, (int) $res->CALCULATEDSTATUS);       // Checked in today
        $this->assertSame('118', $res->ROOMNUMBER);
        $this->assertSame('DBL', $res->ROOMTYPE);
        $this->assertSame('BAR', $res->RATECODE);
        $this->assertSame(2, (int) $res->PROFILE_TYPE);           // Individual
        $this->assertSame(4000.0, (float) $res->POSTAMOUNT);
        $this->assertSame('F-555', (string) $res->RESERVATION_ID); // Fidelio no korunur

        $charge = DB::table('pro_isv_postings')->where('POSTING_DC_NUMBER', '100')->first();
        $this->assertSame(1, (int) $charge->CDT);
        $this->assertSame(4000.0, (float) $charge->POSTING_SUMUPAMOUNT);
        $this->assertSame('118', $charge->ROOMNUMBER);

        $payment = DB::table('pro_isv_postings')->where('POSTING_DC_NUMBER', '900')->first();
        $this->assertSame(5, (int) $payment->CDT);
        $this->assertSame(0, (int) $payment->PAYMENT_TYPE);       // Nakit
        $this->assertSame(4000.0, (float) $payment->POSTING_SUMUPAMOUNT); // Fidelio'daki gibi pozitif

        $profile = DB::table('pro_isv_profileinfo')->where('PROFILE_ID', $f['profile'])->first();
        $this->assertSame(4, (int) $profile->PROFILE_INTERNALCATEGORY);
        $this->assertSame(2, (int) $profile->SEX);
        $this->assertSame('ayse@example.com', $profile->EMAIL);

        $revenue = DB::table('pro_isv_reservationrevenue')->first();
        $this->assertSame(4000.0, (float) $revenue->GROSSROOM);
        $this->assertSame('25', (string) $revenue->CDAY);

        $this->assertSame(1, DB::table('pro_isv_sample_inhouse')->count());
        $this->assertSame(1, DB::table('pro_giykimbil')->count());
    }

    /** @return array<int,string> */
    private function columns(string $view): array
    {
        $stmt = DB::connection()->getPdo()->query("SELECT * FROM {$view} LIMIT 0");
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $columns[] = $stmt->getColumnMeta($i)['name'];
        }

        return $columns;
    }

    private function fixture(): array
    {
        DB::table('pms_currencies')->insert(['code' => 'TRY', 'name' => 'Türk Lirası']);
        DB::table('pms_countries')->insert(['code' => 'CY', 'name_tr' => 'Kıbrıs', 'name_en' => 'Cyprus', 'fidelio_code' => 'CY']);
        $p = DB::table('pms_properties')->insertGetId(['code' => 'RCK', 'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-11-25']);
        $type = DB::table('pms_room_types')->insertGetId(['property_id' => $p, 'code' => 'DBL', 'name' => 'Double']);
        $room = DB::table('pms_rooms')->insertGetId(['property_id' => $p, 'room_type_id' => $type, 'number' => '118']);
        $g1 = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'ROOM', 'name' => 'Konaklama']);
        $g2 = DB::table('pms_transaction_code_groups')->insertGetId(['property_id' => $p, 'code' => 'PAY', 'name' => 'Ödemeler', 'type' => 'payment']);
        $roomCode = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $g1, 'code' => '100', 'name' => 'Room', 'revenue_bucket' => 'room']);
        $cash = DB::table('pms_transaction_codes')->insertGetId(['property_id' => $p, 'group_id' => $g2, 'code' => '900', 'name' => 'Cash', 'type' => 'payment', 'payment_kind' => 'cash']);
        $rate = DB::table('pms_rate_codes')->insertGetId(['property_id' => $p, 'code' => 'BAR', 'name' => 'Raf', 'currency_code' => 'TRY', 'room_transaction_code_id' => $roomCode]);
        $seg = DB::table('pms_market_segments')->insertGetId(['property_id' => $p, 'code' => 'IND', 'name' => 'Bireysel']);
        $src = DB::table('pms_source_codes')->insertGetId(['property_id' => $p, 'code' => 'DIR', 'name' => 'Doğrudan']);
        $rty = DB::table('pms_reservation_types')->insertGetId(['property_id' => $p, 'code' => 'GTD', 'name' => 'Garantili']);
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $p, 'type' => 'individual', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz',
            'display_name' => 'Yılmaz, Ayşe', 'gender' => 'female', 'nationality_code' => 'CY']);
        DB::table('pms_profile_communications')->insert(['profile_id' => $profile, 'type' => 'email', 'value' => 'ayse@example.com', 'is_primary' => true]);
        $res = DB::table('pms_reservations')->insertGetId([
            'property_id' => $p, 'confirmation_number' => 'R-1', 'status' => 'in_house', 'reservation_type_id' => $rty, 'profile_id' => $profile,
            'arrival_date' => '2026-11-25', 'departure_date' => '2026-11-27', 'original_departure_date' => '2026-11-27', 'nights' => 2,
            'check_in_business_date' => '2026-11-25', 'checked_in_at' => '2026-11-25 15:00:00',
            'room_type_id' => $type, 'charge_room_type_id' => $type, 'room_id' => $room, 'adults' => 2, 'rate_code_id' => $rate,
            'currency_code' => 'TRY', 'market_segment_id' => $seg, 'source_code_id' => $src, 'legacy_system' => 'fidelio', 'legacy_id' => 'F-555',
        ]);
        $nightIds = [];
        foreach (['2026-11-25', '2026-11-26'] as $d) {
            $nightIds[] = DB::table('pms_reservation_nights')->insertGetId(['reservation_id' => $res, 'property_id' => $p, 'stay_date' => $d,
                'room_type_id' => $type, 'charge_room_type_id' => $type, 'room_id' => $room, 'rate_code_id' => $rate, 'adults' => 2,
                'amount' => 4000, 'net_amount' => 3700, 'currency_code' => 'TRY', 'market_segment_id' => $seg]);
        }
        $folio = DB::table('pms_folios')->insertGetId(['property_id' => $p, 'folio_number' => 'F-1', 'reservation_id' => $res, 'window' => 1, 'opened_business_date' => '2026-11-25']);
        DB::table('pms_postings')->insert([
            ['property_id' => $p, 'business_date' => '2026-11-25', 'folio_id' => $folio, 'reservation_id' => $res, 'room_id' => $room, 'transaction_code_id' => $roomCode,
                'kind' => 'charge', 'description' => 'Room', 'amount' => 4000, 'net_amount' => 3700, 'tax_amount' => 300, 'reservation_night_id' => $nightIds[0], 'stay_date' => '2026-11-25'],
            ['property_id' => $p, 'business_date' => '2026-11-25', 'folio_id' => $folio, 'reservation_id' => $res, 'room_id' => $room, 'transaction_code_id' => $cash,
                'kind' => 'payment', 'description' => 'Cash', 'amount' => -4000, 'net_amount' => -4000, 'tax_amount' => 0, 'reservation_night_id' => null, 'stay_date' => null],
        ]);

        return ['profile' => $profile];
    }
}
