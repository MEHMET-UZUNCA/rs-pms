<?php

namespace Tests\Feature\Domain;

use Database\Seeders\RocksHotelReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rocks Hotel tanım verileri yüklenmiş, örnek oda tipi/oda/fiyat kodu/rezervasyon üretebilen test tabanı. */
abstract class DomainTestCase extends TestCase
{
    use RefreshDatabase;

    protected int $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RocksHotelReferenceSeeder::class);
        $this->property = (int) DB::table('pms_properties')->where('code', 'RCK')->value('id');
        DB::table('pms_properties')->where('id', $this->property)->update(['current_business_date' => '2026-12-01']);
    }

    protected function code(string $code): int
    {
        return (int) DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    protected function roomType(string $code = 'DBL', int $rooms = 2): int
    {
        $type = DB::table('pms_room_types')->insertGetId(['property_id' => $this->property, 'code' => $code, 'name' => $code, 'beds' => 2]);
        for ($i = 1; $i <= $rooms; $i++) {
            DB::table('pms_rooms')->insert(['property_id' => $this->property, 'room_type_id' => $type, 'number' => $code.$i]);
        }

        return $type;
    }

    protected function rateCode(string $code = 'BAR', array $extra = []): int
    {
        return DB::table('pms_rate_codes')->insertGetId(array_merge([
            'property_id' => $this->property, 'code' => $code, 'name' => $code, 'currency_code' => 'TRY', 'room_transaction_code_id' => $this->code('100'),
        ], $extra));
    }

    protected function reservation(int $roomType, int $rate, string $arrival, int $nights, string $status = 'confirmed', bool $deducts = true): int
    {
        $type = DB::table('pms_reservation_types')->where('property_id', $this->property)->where('deducts_inventory', $deducts)->value('id')
            ?? DB::table('pms_reservation_types')->insertGetId(['property_id' => $this->property, 'code' => $deducts ? 'GTD' : 'OPT', 'name' => $deducts ? 'Garantili' : 'Opsiyon', 'deducts_inventory' => $deducts]);
        $segment = DB::table('pms_market_segments')->where('property_id', $this->property)->value('id')
            ?? DB::table('pms_market_segments')->insertGetId(['property_id' => $this->property, 'code' => 'IND', 'name' => 'Bireysel']);
        $source = DB::table('pms_source_codes')->where('property_id', $this->property)->value('id')
            ?? DB::table('pms_source_codes')->insertGetId(['property_id' => $this->property, 'code' => 'DIR', 'name' => 'Doğrudan']);
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'individual', 'display_name' => 'Örnek, Misafir']);
        $departure = date('Y-m-d', strtotime($arrival." +{$nights} day"));
        $res = DB::table('pms_reservations')->insertGetId([
            'property_id' => $this->property, 'confirmation_number' => 'R-'.uniqid(), 'status' => $status, 'reservation_type_id' => $type,
            'profile_id' => $profile, 'arrival_date' => $arrival, 'departure_date' => $departure, 'original_departure_date' => $departure,
            'nights' => $nights, 'room_type_id' => $roomType, 'charge_room_type_id' => $roomType, 'adults' => 2, 'rate_code_id' => $rate,
            'currency_code' => 'TRY', 'market_segment_id' => $segment, 'source_code_id' => $source,
        ]);
        for ($i = 0; $i < $nights; $i++) {
            DB::table('pms_reservation_nights')->insert([
                'reservation_id' => $res, 'property_id' => $this->property, 'stay_date' => date('Y-m-d', strtotime($arrival." +{$i} day")),
                'room_type_id' => $roomType, 'charge_room_type_id' => $roomType, 'rate_code_id' => $rate, 'adults' => 2,
                'currency_code' => 'TRY', 'market_segment_id' => $segment,
            ]);
        }

        return $res;
    }
}
