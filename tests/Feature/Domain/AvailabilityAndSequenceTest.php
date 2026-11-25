<?php

namespace Tests\Feature\Domain;

use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Rates\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AvailabilityAndSequenceTest extends DomainTestCase
{
    public function test_inventory_counts_rooms_outages_sales_and_blocks(): void
    {
        $type = $this->roomType('DBL', 5);
        $rate = $this->rateCode();
        $this->reservation($type, $rate, '2026-12-01', 2, 'confirmed');
        $this->reservation($type, $rate, '2026-12-02', 1, 'in_house');
        $this->reservation($type, $rate, '2026-12-01', 1, 'cancelled');
        $this->reservation($type, $rate, '2026-12-01', 1, 'tentative', false);
        $room = DB::table('pms_rooms')->where('room_type_id', $type)->value('id');
        $reason = DB::table('pms_reason_codes')->insertGetId(['property_id' => $this->property, 'category' => 'outage', 'code' => 'AC', 'name' => 'Klima']);
        DB::table('pms_room_outages')->insert(['property_id' => $this->property, 'room_id' => $room, 'type' => 'ooo', 'date_from' => '2026-12-02', 'date_to' => '2026-12-03', 'reason_code_id' => $reason]);
        $agent = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'travel_agent', 'display_name' => 'Örnek Acente']);
        $block = DB::table('pms_blocks')->insertGetId(['property_id' => $this->property, 'code' => 'ALT1', 'name' => 'Allotman', 'status' => 'definite', 'profile_id' => $agent, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03']);
        DB::table('pms_block_inventory')->insert(['block_id' => $block, 'room_type_id' => $type, 'date' => '2026-12-01', 'allotted' => 2, 'picked_up' => 1]);

        (new AvailabilityService)->rebuild($this->property, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-02'));

        $d1 = DB::table('pms_inventory_daily')->where('room_type_id', $type)->where('date', '2026-12-01')->first();
        $this->assertSame(5, (int) $d1->physical_rooms);
        $this->assertSame(1, (int) $d1->sold_definite);        // iptal sayılmaz
        $this->assertSame(1, (int) $d1->sold_tentative);       // opsiyon envanterden düşmez
        $this->assertSame(1, (int) $d1->block_remaining_definite);
        $this->assertSame(3, (int) $d1->available);            // 5 − 1 satış − 1 blok

        $d2 = DB::table('pms_inventory_daily')->where('room_type_id', $type)->where('date', '2026-12-02')->first();
        $this->assertSame(1, (int) $d2->out_of_order);
        $this->assertSame(2, (int) $d2->sold_definite);
        $this->assertSame(2, (int) $d2->available);            // 5 − 1 arızalı − 2 satış
    }

    public function test_number_sequence_is_gapless_and_resets_yearly(): void
    {
        $seq = new NumberSequenceService;
        DB::table('pms_number_sequences')->insert(['property_id' => $this->property, 'type' => 'invoice', 'prefix' => 'RCK', 'next_number' => 41, 'padding' => 6, 'reset_yearly' => true, 'current_year' => 2026]);

        $this->assertSame('RCK000041', $seq->next($this->property, 'invoice', 2026));
        $this->assertSame('RCK000042', $seq->next($this->property, 'invoice', 2026));
        $this->assertSame('RCK000001', $seq->next($this->property, 'invoice', 2027));
        $this->assertSame('000001', $seq->next($this->property, 'folio'));
    }
}
