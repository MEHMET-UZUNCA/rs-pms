<?php

namespace Tests\Feature\Domain;

use App\Domain\Migration\FidelioImporter;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class FidelioImportTest extends DomainTestCase
{
    private string $dir;

    private array $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoDataSeeder::class);
        $this->dir = sys_get_temp_dir().'/rspms-fidelio-'.uniqid();
        mkdir($this->dir);
        $first = fn (string $table) => (string) DB::table($table)->where('property_id', $this->property)->orderBy('id')->value('code');
        $type = DB::table('pms_room_types')->where('property_id', $this->property)->orderBy('id')->first();
        $this->codes = [
            'rate' => $first('pms_rate_codes'), 'market' => $first('pms_market_segments'), 'source' => $first('pms_source_codes'),
            'type' => $type->code, 'room' => (string) DB::table('pms_rooms')->where('room_type_id', $type->id)->whereNull('current_reservation_id')->orderBy('id')->value('number'),
        ];
        $this->writeFiles();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function csv(string $name, array $rows, string $delimiter = ';'): void
    {
        $h = fopen($this->dir.'/'.$name, 'w');
        fputcsv($h, array_keys($rows[0]), $delimiter, '"', '');
        foreach ($rows as $row) {
            fputcsv($h, array_values($row), $delimiter, '"', '');
        }
        fclose($h);
    }

    private function writeFiles(): void
    {
        $p = fn ($id, $cat, $n1, $n2 = '', $extra = []) => array_merge(['PROFILE_ID' => $id, 'PROFILE_TYPE' => $cat === 4 ? 2 : 1, 'PROFILE_INTERNALCATEGORY' => $cat,
            'GUESTFULLNAME' => trim("$n1 $n2"), 'NAME1' => $n1, 'NAME2' => $n2, 'NAME3' => '', 'SEX' => '', 'PROFILELANGUAGE' => '', 'BIRTHDATE' => '',
            'STREET1' => '', 'CITY' => '', 'COUNTRY' => '', 'NATION' => '', 'EMAIL' => '', 'TELEPHONE' => '', 'MOBILETELEPHONE' => '', 'VIPCODE' => '',
            'ACTIVE_STATUS' => 1, 'MERGEDTO_PROFILE_ID' => '', 'PERSONAL_DOC_TYPE' => '', 'PERSONAL_DOC_NUMBER' => '', 'TCIDNO' => '', 'CREATIONDATE' => '14.03.2009'], $extra);
        $this->csv('PRO_ISV_PROFILEINFO.csv', [
            $p(501, 4, 'Müller', 'Hans', ['SEX' => 1, 'PROFILELANGUAGE' => 'GER', 'BIRTHDATE' => '1970-05-04', 'STREET1' => 'Hauptstr. 1', 'CITY' => 'Berlin',
                'COUNTRY' => 'DEU', 'NATION' => 'DE', 'EMAIL' => 'Hans@Example.de', 'MOBILETELEPHONE' => '+49 170 123', 'PERSONAL_DOC_TYPE' => 'PASSPORT', 'PERSONAL_DOC_NUMBER' => 'C01X00T47']),
            $p(502, 2, 'Sun Travel'),
            $p(503, 4, 'Muller', 'Hans', ['ACTIVE_STATUS' => 4, 'MERGEDTO_PROFILE_ID' => 501]),
            $p(504, 4, 'Yılmaz', 'Ayşe', ['SEX' => 2, 'NATION' => 'TUR', 'TCIDNO' => '12345678901']),
        ]);

        $r = fn ($id, $date, $arr, $dep, $calc, $extra = []) => array_merge(['RESERVATION_ID' => $id, 'DETAIL_ID' => $id * 100 + (int) substr($date, -2), 'DETAIL_DATE' => $date,
            'ARRIVALDATE' => $arr, 'DEPARTUREDATE' => $dep, 'ROOMNUMBER' => '', 'ROOMTYPE' => $this->codes['type'], 'RATEROOMTYPE' => $this->codes['type'],
            'NOOFROOMS' => 1, 'NOOFADULTS' => 2, 'CHILDREN' => 0, 'RATECODE' => $this->codes['rate'], 'MARKETCODE' => $this->codes['market'],
            'SOURCECODE' => $this->codes['source'], 'CURRENCY' => 'TRY', 'RATECODEAMOUNT' => '4.500,00', 'RATEMANUAL' => '', 'PROFILE_ID' => 501,
            'AGENTNAME_ID' => 502, 'GUARANTEECODE' => 'XX', 'RESERVATIONSTATUS' => 1, 'CALCULATEDSTATUS' => $calc, 'REMARKS' => '', 'CH' => '',
            'EXPECTED_ARRIVALTIME' => '15:30', 'CREATED_USERNAME' => 'FO1'], $extra);
        $this->csv('PRO_ISV_RESERVATIONINFO.csv', [
            // ileri tarihli, 2 gece
            $r(9001, '10.12.2026', '10.12.2026', '12.12.2026', 0, ['REMARKS' => 'Deniz manzarası']),
            $r(9001, '11.12.2026', '10.12.2026', '12.12.2026', 0, ['RATEMANUAL' => '4000']),
            // içeride, oda atanmış
            $r(9002, '30.11.2026', '30.11.2026', '03.12.2026', 3, ['ROOMNUMBER' => $this->codes['room'], 'PROFILE_ID' => 504, 'AGENTNAME_ID' => '']),
            $r(9002, '01.12.2026', '30.11.2026', '03.12.2026', 3, ['ROOMNUMBER' => $this->codes['room'], 'PROFILE_ID' => 504, 'AGENTNAME_ID' => '']),
            $r(9002, '02.12.2026', '30.11.2026', '03.12.2026', 3, ['ROOMNUMBER' => $this->codes['room'], 'PROFILE_ID' => 504, 'AGENTNAME_ID' => '']),
            // geçmiş (çıkmış) — aktarılmaz
            $r(9003, '01.11.2026', '01.11.2026', '02.11.2026', 6),
            // ileri tarihli iptal — varsayılan aktarılmaz
            $r(9004, '20.12.2026', '20.12.2026', '21.12.2026', 12),
            // olmayan fiyat kodu — hata
            $r(9005, '15.12.2026', '15.12.2026', '16.12.2026', 0, ['RATECODE' => 'YOKKOD']),
            // iki odalı satır
            $r(9006, '18.12.2026', '18.12.2026', '19.12.2026', 1, ['NOOFROOMS' => 2]),
        ]);
        $this->csv('PRO_ISV_MULTIGUEST_LIST.csv', [['RESERVATION_ID' => 9002, 'MULTIGUEST_PROFILE_ID' => 501], ['RESERVATION_ID' => 9002, 'MULTIGUEST_PROFILE_ID' => 504]], ',');
        $post = fn ($id, $cdt, $amount, $ledger = 0, $window = 77) => ['POSTING_ID' => $id, 'POSTING_RES_ID' => 9002, 'POSTING_WINDOW_ID' => $window, 'CDT' => $cdt,
            'POSTING_SUMUPAMOUNT' => $amount, 'POSTING_LEDGERSTATUS' => $ledger, 'POSTING_DC_NUMBER' => $cdt === 1 ? 100 : 900];
        $this->csv('PRO_ISV_POSTINGS.csv', [$post(1, 1, '4500'), $post(2, 1, '4500'), $post(3, 5, '3000'), $post(4, 5, '999', 1), $post(5, 1, '250,50', 0, 78)], "\t");
    }

    public function test_imports_profiles_reservations_guests_and_balances_idempotently(): void
    {
        $this->assertSame(0, Artisan::call('pms:import-fidelio', ['dir' => $this->dir]));
        $output = Artisan::output();
        $this->assertStringContainsString('YOKKOD', $output);   // eksik kod raporda

        // Profiller
        $hans = DB::table('pms_profiles')->where('legacy_system', 'fidelio')->where('legacy_id', '501')->first();
        $this->assertSame('individual', $hans->type);
        $this->assertSame('Müller, Hans', $hans->display_name);
        $this->assertSame(['male', 'de', 'DE', '1970-05-04'], [$hans->gender, $hans->language_code, $hans->nationality_code, substr($hans->birth_date, 0, 10)]);
        $this->assertSame('DE', DB::table('pms_profile_addresses')->where('profile_id', $hans->id)->value('country_code'));   // DEU → DE
        $this->assertSame('hans@example.de', DB::table('pms_profile_communications')->where('profile_id', $hans->id)->where('type', 'email')->value('value_normalized'));
        $doc = DB::table('pms_profile_identities')->where('profile_id', $hans->id)->first();
        $this->assertSame(['passport', 'C01X00T47'], [$doc->document_type, Crypt::decryptString($doc->document_number)]);
        $this->assertSame('travel_agent', DB::table('pms_profiles')->where('legacy_id', '502')->value('type'));
        $merged = DB::table('pms_profiles')->where('legacy_id', '503')->first();
        $this->assertSame(['merged', (int) $hans->id], [$merged->status, (int) $merged->merged_into_id]);
        $ayse = DB::table('pms_profiles')->where('legacy_id', '504')->first();
        $this->assertSame(['female', 'TR', 'tc_id'], [$ayse->gender, $ayse->nationality_code, DB::table('pms_profile_identities')->where('profile_id', $ayse->id)->value('document_type')]);

        // Rezervasyonlar
        $future = DB::table('pms_reservations')->where('legacy_system', 'fidelio')->where('legacy_id', '9001')->first();
        $this->assertSame(['confirmed', '9001', 2, 'Deniz manzarası', '15:30:00'], [$future->status, $future->confirmation_number, (int) $future->nights, $future->guest_comments, substr((string) $future->eta, 0, 8)]);
        $this->assertSame((int) DB::table('pms_profiles')->where('legacy_id', '502')->value('id'), (int) $future->agent_profile_id);
        $this->assertEquals(8500.0, (float) $future->total_room_amount);   // 4.500,00 + elle 4000
        $this->assertTrue((bool) $future->is_rate_overridden);
        $this->assertSame(2, DB::table('pms_reservation_nights')->where('reservation_id', $future->id)->count());
        $this->assertNotNull($future->reservation_type_id);   // XX yok → varsayılan garantili tip

        $inHouse = DB::table('pms_reservations')->where('legacy_id', '9002')->first();
        $room = DB::table('pms_rooms')->where('id', $inHouse->room_id)->first();
        $this->assertSame(['in_house', $this->codes['room'], 'occupied', (int) $inHouse->id], [$inHouse->status, $room->number, $room->fo_status, (int) $room->current_reservation_id]);
        $this->assertSame(1, DB::table('pms_reservation_guests')->where('reservation_id', $inHouse->id)->count());   // ana misafir ikinci kez eklenmez

        $this->assertFalse(DB::table('pms_reservations')->whereIn('legacy_id', ['9003', '9004', '9005'])->exists());
        $this->assertSame(2, DB::table('pms_reservations')->whereIn('legacy_id', ['9006', '9006-2'])->whereNotNull('booking_id')->distinct()->count('booking_id') + 1);
        $this->assertGreaterThan(9006, (int) DB::table('pms_number_sequences')->where('property_id', $this->property)->where('type', 'confirmation')->value('next_number'));

        // Bakiyeler: pencere 77 → 4500+4500−3000 = 6000 (defter 1 hariç), pencere 78 → 250,50
        $folios = DB::table('pms_folios')->where('reservation_id', $inHouse->id)->orderBy('window')->get();
        $this->assertSame([1, 2], $folios->pluck('window')->map(fn ($w) => (int) $w)->all());
        $this->assertEquals([6000.0, 250.5], $folios->pluck('balance')->map(fn ($b) => (float) $b)->all());
        $this->assertSame('import', DB::table('pms_postings')->where('folio_id', $folios[0]->id)->value('source'));

        $this->assertSame(1, DB::table('pms_legacy_import_errors')->count());
        $this->assertSame(4, DB::table('pms_legacy_import_batches')->count());

        // Tekrar: çift kayıt yok
        $counts = fn () => [DB::table('pms_profiles')->where('legacy_system', 'fidelio')->count(), DB::table('pms_reservations')->where('legacy_system', 'fidelio')->count(),
            DB::table('pms_reservation_nights')->whereIn('reservation_id', DB::table('pms_reservations')->where('legacy_system', 'fidelio')->select('id'))->count(),
            DB::table('pms_postings')->where('source', 'import')->count(), DB::table('pms_reservation_guests')->count(), DB::table('pms_profile_identities')->count()];
        $before = $counts();
        Artisan::call('pms:import-fidelio', ['dir' => $this->dir]);
        $this->assertSame($before, $counts());
        $this->assertEquals(6000.0, (float) DB::table('pms_folios')->where('id', $folios[0]->id)->value('balance'));
    }

    public function test_dry_run_writes_nothing_and_cancelled_option(): void
    {
        $before = DB::table('pms_profiles')->count();
        Artisan::call('pms:import-fidelio', ['dir' => $this->dir, '--dry-run' => true]);
        $this->assertStringContainsString('DENEME', Artisan::output());
        $this->assertSame($before, DB::table('pms_profiles')->count());
        $this->assertSame(0, DB::table('pms_legacy_import_batches')->count());

        Artisan::call('pms:import-fidelio', ['dir' => $this->dir, '--only' => 'profiles,reservations', '--with-cancelled' => true]);
        $this->assertSame('cancelled', DB::table('pms_reservations')->where('legacy_id', '9004')->value('status'));
        $this->assertSame(0, DB::table('pms_postings')->where('source', 'import')->count());
    }

    public function test_parsers(): void
    {
        $this->assertSame('2026-12-01', FidelioImporter::date('01-DEC-26'));
        $this->assertSame('2026-12-01', FidelioImporter::date('01.12.2026'));
        $this->assertSame('2026-12-01 14:05:00', FidelioImporter::dateTime('2026-12-01 14:05:00.000'));
        $this->assertSame(1234.56, FidelioImporter::number('1.234,56'));
        $this->assertSame(1234.56, FidelioImporter::number('1,234.56'));
        $this->assertSame(-12.5, FidelioImporter::number('-12,5'));
        $this->assertSame('09:30:00', FidelioImporter::time('01.12.2026 09:30'));
    }
}
