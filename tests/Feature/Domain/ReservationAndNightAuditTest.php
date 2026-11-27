<?php

namespace Tests\Feature\Domain;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Services\TaxRateService;
use App\Domain\Inventory\Models\Room;
use App\Domain\NightAudit\Exceptions\NightAuditException;
use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Reservations\Exceptions\ReservationException;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\FixedChargeService;
use App\Domain\Reservations\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReservationAndNightAuditTest extends DomainTestCase
{
    private ReservationService $reservations;

    private int $type;

    private int $rate;

    private array $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservations = app(ReservationService::class);
        app(TaxRateService::class)->importFidelioRates($this->property, ['100' => 10, '110' => 10, '130' => 10], CarbonImmutable::parse('2026-01-01'));
        $this->type = $this->roomType('DBL', 3);
        $hb = DB::table('pms_packages')->insertGetId(['property_id' => $this->property, 'code' => 'HB', 'name' => 'Yarım pansiyon', 'is_included_in_rate' => true]);
        DB::table('pms_package_elements')->insert([
            ['package_id' => $hb, 'transaction_code_id' => $this->code('110'), 'calc' => 'per_person', 'amount_adult' => 400, 'amount_child' => 200],
            ['package_id' => $hb, 'transaction_code_id' => $this->code('130'), 'calc' => 'per_person', 'amount_adult' => 900, 'amount_child' => 450],
        ]);
        $this->rate = $this->rateCode('TA-HB', ['package_id' => $hb]);
        DB::table('pms_rate_amounts')->insert(['rate_code_id' => $this->rate, 'room_type_id' => $this->type, 'date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'price_2' => 6000]);
        $this->codes = [
            'reservation_type_id' => DB::table('pms_reservation_types')->insertGetId(['property_id' => $this->property, 'code' => 'GTD', 'name' => 'Garantili']),
            'market_segment_id' => DB::table('pms_market_segments')->insertGetId(['property_id' => $this->property, 'code' => 'TOA', 'name' => 'Tur operatörü']),
            'source_code_id' => DB::table('pms_source_codes')->insertGetId(['property_id' => $this->property, 'code' => 'AGT', 'name' => 'Acente']),
        ];
    }

    private function book(string $arrival = '2026-12-01', string $departure = '2026-12-03', array $extra = []): Reservation
    {
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'individual', 'display_name' => 'Örnek, Misafir']);

        return $this->reservations->create(array_merge($this->codes, [
            'property_id' => $this->property, 'profile_id' => $profile, 'room_type_id' => $this->type, 'rate_code_id' => $this->rate,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'adults' => 2,
        ], $extra));
    }

    private function room(string $number = 'DBL1'): Room
    {
        return Room::query()->where('number', $number)->firstOrFail();
    }

    public function test_create_fills_nightly_rates_and_availability(): void
    {
        $res = $this->book();

        $this->assertSame('000001', $res->confirmation_number);
        $this->assertCount(2, $res->nightRows);
        $this->assertSame('6000.00', (string) $res->nightRows[0]->amount);
        $this->assertSame('2600.00', (string) $res->nightRows[0]->package_amount);   // 2 × (400 + 900)
        $this->assertSame('5454.55', (string) $res->nightRows[0]->net_amount);       // %10 KDV dahil
        $this->assertSame('12000.00', (string) $res->total_room_amount);
        $this->assertSame(2, (int) DB::table('pms_inventory_daily')->where('room_type_id', $this->type)->where('date', '2026-12-01')->value('available'));
    }

    public function test_missing_rate_is_rejected_and_manual_rate_is_kept_on_date_change(): void
    {
        try {
            $this->book('2027-01-05', '2027-01-06');
            $this->fail('Fiyatı olmayan tarihe rezervasyon açıldı');
        } catch (ReservationException) {
        }

        $res = $this->book('2026-12-10', '2026-12-12', ['manual_amount' => 5000]);
        $res = $this->reservations->changeDates($res, '2026-12-10', '2026-12-13');
        $this->assertSame(['5000.00', '5000.00', '6000.00'], $res->nightRows->sortBy('stay_date')->pluck('amount')->map(fn ($a) => (string) $a)->values()->all());
    }

    public function test_room_cannot_be_double_booked(): void
    {
        $a = $this->book();
        $b = $this->book('2026-12-02', '2026-12-04');
        $this->reservations->assignRoom($a, $this->room());

        $this->expectException(ReservationException::class);
        $this->reservations->assignRoom($b, $this->room());
    }

    public function test_check_in_night_audit_and_check_out_full_cycle(): void
    {
        $res = $this->book();
        $this->reservations->assignRoom($res, $this->room());
        $res = $this->reservations->checkIn($res->fresh());
        $this->assertSame('in_house', $res->status);
        $this->assertSame('occupied', $this->room()->fo_status);
        $folio = Folio::query()->where('reservation_id', $res->id)->where('window', 1)->firstOrFail();

        $noShow = $this->book('2026-12-01', '2026-12-02');            // gelmeyecek misafir
        NightAuditAnswers::save($this->property, ['arrivals_pending' => 'continue'], null);   // Fidelio cevabı: Continue → No Show
        app(NightAuditService::class)->run($this->property);

        // Oda ücreti: 6000 = 3400 oda + 800 kahvaltı + 1800 akşam yemeği
        $codes = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $folio->id)->pluck('p.amount', 't.code')->map(fn ($a) => (float) $a)->sortKeys()->all();
        $this->assertSame(['100' => 3400.0, '110' => 800.0, '130' => 1800.0], $codes);
        $this->assertSame('6000.00', (string) $folio->fresh()->balance);
        $this->assertSame('no_show', $noShow->fresh()->status);
        $this->assertSame('2026-12-02', DB::table('pms_properties')->where('id', $this->property)->value('current_business_date'));
        $stats = DB::table('pms_daily_statistics')->where('business_date', '2026-12-01')->where('dimension', 'total')->first();
        $this->assertSame(1, (int) $stats->rooms_sold);
        $this->assertSame(155 + 3, (int) $stats->rooms_available);   // Rocks Hotel'in 155 aktif odası + testin 3 odası
        $this->assertSame(0.63, (float) $stats->occupancy_pct);
        $this->assertNotNull(DB::table('pms_night_audit_runs')->where('business_date', '2026-12-01')->value('report_pack'));

        // 2. gece de kapanır (iş günü 02.12 → 03.12); aynı gece ikinci kez yazılmaz
        app(NightAuditService::class)->run($this->property);
        $this->assertSame(6, DB::table('pms_postings')->where('folio_id', $folio->id)->count());
        $this->assertSame('12000.00', (string) $folio->fresh()->balance);

        // Çıkış günü bakiye ödenmeden çıkış yapılamaz
        try {
            $this->reservations->checkOut($res->fresh());
            $this->fail('Bakiyesi açık folyoyla çıkış yapıldı');
        } catch (ReservationException) {
        }
        app(PostingService::class)->payment($folio->fresh(), TransactionCode::find($this->code('910')), 12000);
        $res = $this->reservations->checkOut($res->fresh());

        $this->assertSame('checked_out', $res->status);
        $this->assertSame('closed', $folio->fresh()->status);
        $this->assertSame('dirty', $this->room()->hk_status);
        $this->assertSame('vacant', $this->room()->fo_status);
    }

    public function test_night_audit_posts_fixed_charges(): void
    {
        $res = $this->book('2026-12-01', '2026-12-04');
        $this->reservations->assignRoom($res, $this->room());
        $res = $this->reservations->checkIn($res->fresh());
        $fixed = app(FixedChargeService::class);
        $code = $this->code('110');
        $fixed->add($res, ['transaction_code_id' => $code, 'description' => 'Otopark', 'quantity' => 2, 'amount' => 50, 'frequency' => 'daily', 'date_from' => '2026-12-01', 'date_to' => '2026-12-03']);
        $fixed->add($res, ['transaction_code_id' => $code, 'description' => 'Ek yatak', 'quantity' => 1, 'amount' => 150, 'frequency' => 'once', 'date_from' => '2026-12-02', 'date_to' => '2026-12-03']);
        $fixed->add($res, ['transaction_code_id' => $code, 'description' => 'Haftalık', 'quantity' => 1, 'amount' => 30, 'frequency' => 'weekly', 'date_from' => '2026-12-01', 'date_to' => '2026-12-03']);
        try {
            $fixed->add($res, ['transaction_code_id' => $code, 'quantity' => 1, 'amount' => 10, 'frequency' => 'daily', 'date_from' => '2026-12-01', 'date_to' => '2026-12-04']);
            $this->fail('Çıkış gününe sabit ücret eklendi');
        } catch (ReservationException) {
        }

        foreach (range(1, 3) as $night) {
            app(NightAuditService::class)->run($this->property);
        }
        $posted = DB::table('pms_postings')->where('source', 'fixed_charge')->orderBy('stay_date')->orderBy('id')->get(['stay_date', 'amount', 'quantity', 'description']);
        $this->assertSame([['2026-12-01', 100.0, 'Otopark'], ['2026-12-01', 30.0, 'Haftalık'], ['2026-12-02', 100.0, 'Otopark'], ['2026-12-02', 150.0, 'Ek yatak'], ['2026-12-03', 100.0, 'Otopark']],
            $posted->map(fn ($p) => [substr((string) $p->stay_date, 0, 10), (float) $p->amount, $p->description])->all());
        $this->assertSame('2026-12-03', substr((string) DB::table('pms_fixed_charges')->where('description', 'Otopark')->value('last_posted_date'), 0, 10));
        $this->assertSame(1, DB::table('pms_night_audit_steps')->where('code', 'room_charges')->where('result', 'like', '%"fixed_charges_posted":1%')->count());   // 3. gece
    }

    public function test_only_check_out_and_room_move_make_rooms_dirty(): void
    {
        Room::query()->whereIn('number', ['DBL1', 'DBL2'])->update(['hk_status' => 'inspected']);
        $res = $this->book();
        $this->reservations->assignRoom($res, $this->room());
        $res = $this->reservations->checkIn($res->fresh());
        $this->assertSame('inspected', $this->room()->hk_status);              // giriş kat durumunu değiştirmez

        app(NightAuditService::class)->run($this->property);
        $this->assertSame('inspected', $this->room()->hk_status);              // gece kapanışı da değiştirmez

        $this->reservations->assignRoom($res->fresh(), $this->room('DBL2'));   // oda değişimi
        $this->assertSame('dirty', $this->room()->hk_status);                  // eski oda Dirty
        $this->assertSame('vacant', $this->room()->fo_status);
        $this->assertSame('inspected', $this->room('DBL2')->hk_status);
        $this->assertSame('occupied', $this->room('DBL2')->fo_status);
    }

    public function test_night_audit_is_held_when_a_departure_is_still_in_house(): void
    {
        $res = $this->book('2026-12-01', '2026-12-02');
        $this->reservations->assignRoom($res, $this->room());
        $this->reservations->checkIn($res->fresh());
        app(NightAuditService::class)->run($this->property);     // 01.12 kapanır, misafir 02.12'de çıkmalı

        try {
            app(NightAuditService::class)->run($this->property);
            $this->fail('Çıkışı geçmiş misafir varken kapanış yapıldı');
        } catch (NightAuditException $e) {
            $this->assertStringContainsString('departures_pending', $e->getMessage());
        }
        $this->assertSame('held', DB::table('pms_night_audit_runs')->where('business_date', '2026-12-02')->value('status'));

        // Amir onayıyla geçilebilir
        app(NightAuditService::class)->run($this->property, null, ['departures_pending' => 'Geç çıkış onaylandı']);
        $this->assertSame('completed', DB::table('pms_night_audit_runs')->where('business_date', '2026-12-02')->value('status'));
    }
}
