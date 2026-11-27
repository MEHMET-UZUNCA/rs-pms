<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ReservationService;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class OverbookingTest extends DomainTestCase
{
    private int $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->type = (int) DB::table('pms_room_types')->where('property_id', $this->property)->where('code', 'YSU')->value('id');
    }

    private function user(string $role, string $name): User
    {
        $u = User::create(['username' => $name, 'name' => $name, 'email' => strtolower($name).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $u->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', $role)->value('id')]);

        return $u;
    }

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    /** YSU'yu 05.12 gecesi için doldurur (bütün odalar kesin rezervasyonlu). */
    private function fillType(): void
    {
        $rooms = DB::table('pms_rooms')->where('room_type_id', $this->type)->where('is_active', true)->where('is_pseudo', false)->count();
        $profile = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'individual', 'display_name' => 'Dolu, Otel']);
        $service = app(ReservationService::class);
        for ($i = 0; $i < $rooms; $i++) {
            $service->create(['property_id' => $this->property, 'profile_id' => $profile, 'room_type_id' => $this->type, 'rate_code_id' => $this->id('pms_rate_codes', 'BAR'),
                'arrival_date' => '2026-12-05', 'departure_date' => '2026-12-06', 'adults' => 2, 'manual_amount' => 5000,
                'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'market_segment_id' => $this->id('pms_market_segments', 'IND'), 'source_code_id' => $this->id('pms_source_codes', 'DIR')]);
        }
    }

    private function book(User $u, array $extra = [])
    {
        return $this->actingAs($u)->post('/rezervasyon', array_merge([
            'last_name' => 'Fazla', 'first_name' => 'Satış', 'arrival_date' => '05.12.2026', 'departure_date' => '06.12.2026', 'adults' => 2, 'children' => 0,
            'room_type_id' => $this->type, 'rate_code_id' => $this->id('pms_rate_codes', 'BAR'), 'manual_amount' => '5000',
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'market_segment_id' => $this->id('pms_market_segments', 'IND'),
            'source_code_id' => $this->id('pms_source_codes', 'DIR'), 'guarantee_type' => 'none',
        ], $extra));
    }

    public function test_overbooking_needs_limit_or_supervisor_confirmation(): void
    {
        $this->fillType();
        $clerk = $this->user('FOA', 'KATIP');
        $sup = $this->user('FOM', 'SEF');
        $count = fn () => Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Fazla'))->count();

        // Dolu tipe kayıt: geri alınır
        $this->book($clerk)->assertSessionHasErrors('reservation')->assertSessionHas('overbooking', true);
        $this->assertSame(0, $count());
        $this->book($clerk, ['overbook' => '1'])->assertSessionHasErrors('reservation');   // yetkisiz onay işlemez
        $this->assertSame(0, $count());

        // Yetkili "yine de kaydet"
        $this->book($sup, ['overbook' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $count());

        // Overbooking sınırı: 1 oda daha uyarısız
        $this->actingAs($sup)->get('/overbooking?bas=05.12.2026')->assertOk()->assertSee('Overbooking')->assertSee('name="limit['.$this->type.'][2026-12-05]"', false);
        $this->actingAs($sup)->post('/overbooking', ['limit' => [$this->type => ['2026-12-05' => '2']]])->assertSessionHas('status');
        $this->assertSame(2, (int) DB::table('pms_overbooking_limits')->where('room_type_id', $this->type)->where('date', '2026-12-05')->value('limit'));
        $this->book($clerk)->assertSessionHasNoErrors();   // -2 ≥ -2
        $this->assertSame(2, $count());
        $this->book($clerk)->assertSessionHasErrors('reservation');   // sınır aşıldı
        $this->assertSame(2, $count());

        // Dönem kaydı ve silme (0)
        $this->actingAs($sup)->post('/overbooking', ['room_types' => [$this->type], 'from' => '10.12.2026', 'to' => '12.12.2026', 'value' => 3])->assertSessionHas('status');
        $this->assertSame(3, DB::table('pms_overbooking_limits')->where('room_type_id', $this->type)->whereBetween('date', ['2026-12-10', '2026-12-12'])->count());
        $this->actingAs($sup)->post('/overbooking', ['room_types' => [$this->type], 'from' => '10.12.2026', 'to' => '12.12.2026', 'value' => 0]);
        $this->assertSame(0, DB::table('pms_overbooking_limits')->where('room_type_id', $this->type)->whereBetween('date', ['2026-12-10', '2026-12-12'])->count());
        $this->actingAs($clerk)->post('/overbooking', ['limit' => [$this->type => ['2026-12-05' => '5']]])->assertForbidden();
    }

    public function test_notes_can_be_edited_on_an_already_overbooked_day(): void
    {
        $this->fillType();
        $sup = $this->user('FOM', 'SEF');
        $this->book($sup, ['overbook' => '1'])->assertSessionHasNoErrors();
        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Fazla'))->firstOrFail();
        $clerk = $this->user('FOA', 'KATIP');
        $this->actingAs($clerk)->put("/rezervasyon/{$r->id}", [
            'arrival_date' => '05.12.2026', 'departure_date' => '06.12.2026', 'adults' => 2, 'room_type_id' => $this->type, 'rate_code_id' => $r->rate_code_id,
            'reservation_type_id' => $r->reservation_type_id, 'market_segment_id' => $r->market_segment_id, 'source_code_id' => $r->source_code_id,
            'guarantee_type' => 'none', 'guest_comments' => 'Geç gelecek',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Geç gelecek', $r->fresh()->guest_comments);
    }

    public function test_early_check_out_moves_departure_to_today(): void
    {
        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Yılmaz'))->firstOrFail();   // içeride, 04.12 çıkış
        DB::table('pms_folios')->where('reservation_id', $r->id)->update(['balance' => 0]);
        $original = $r->departure_date->toDateString();
        app(ReservationService::class)->checkOut($r->fresh());
        $r->refresh();
        $this->assertSame(['2026-12-01', $original], [$r->departure_date->toDateString(), $r->original_departure_date->toDateString()]);
        $this->assertSame('checked_out', $r->status);
    }
}
