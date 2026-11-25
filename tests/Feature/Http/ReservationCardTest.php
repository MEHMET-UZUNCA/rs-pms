<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ReservationCardTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'last_name' => 'Kara', 'first_name' => 'Deniz', 'nationality_code' => 'TR', 'phone' => '+90 533 000 00 99', 'email' => 'deniz@example.com',
            'arrival_date' => '+3', 'departure_date' => '+6', 'adults' => 2, 'children' => 1,
            'room_type_id' => $this->id('pms_room_types', 'DBL'), 'rate_code_id' => $this->id('pms_rate_codes', 'BAR'), 'manual_amount' => '4500',
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'market_segment_id' => $this->id('pms_market_segments', 'IND'),
            'source_code_id' => $this->id('pms_source_codes', 'DIR'), 'guarantee_type' => 'credit_card', 'room_charges_allowed' => '1',
            'guest_comments' => 'Deniz manzaralı oda istiyor',
        ], $override);
    }

    public function test_card_shows_ten_tabs_with_fidelio_shortcuts(): void
    {
        $html = $this->actingAs($this->user)->get('/rezervasyon/yeni')->assertOk()->getContent();
        foreach (['Reservation', 'Inventory', 'Profiles', 'Packages', 'Rate Grid', 'Features', 'Credit Card', 'Fixed Charges', 'Notes', 'Meal Plan'] as $tab) {
            $this->assertStringContainsString($tab, $html);
        }
        foreach (range(0, 9) as $key) {
            $this->assertStringContainsString('data-hotkey="ctrl+'.$key.'"', $html);
        }
        $this->assertStringContainsString('value="01.12.2026"', $html);   // yeni kartta geliş = iş günü
        $this->actingAs($this->user)->get('/rezervasyon')->assertSee(route('reservations.create'), false)->assertSee('data-hotkey="ctrl+n alt+n"', false);
    }

    public function test_new_reservation_with_new_guest_and_fidelio_dates(): void
    {
        $this->actingAs($this->user)->post('/rezervasyon', $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Kara'))->firstOrFail();
        $this->assertSame(['2026-12-04', '2026-12-07', 3, 2, 1], [$r->arrival_date->toDateString(), $r->departure_date->toDateString(), $r->nights, $r->adults, $r->children]);
        $this->assertSame('Kara, Deniz', $r->profile->display_name);
        $this->assertSame(['+90 533 000 00 99', 'deniz@example.com'], $r->profile->communications()->orderBy('id')->pluck('value')->all());
        $this->assertSame(3, $r->nightRows()->count());
        $this->assertSame(13500.0, (float) $r->total_room_amount);
        $this->assertSame('credit_card', $r->guarantee_type);
        $this->assertSame('confirmed', $r->status);

        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}")->assertOk()
            ->assertSee('Kara, Deniz')->assertSee('04.12.2026')->assertSee('4.500,00')->assertSee('Deniz manzaralı oda istiyor');
    }

    public function test_existing_profile_is_found_and_reused(): void
    {
        $this->actingAs($this->user)->getJson('/rezervasyon/profiller?q=yıl')->assertOk()->assertJsonFragment(['display_name' => 'Yılmaz, Ahmet']);
        $this->actingAs($this->user)->getJson('/rezervasyon/profiller?q=Örnek&tur=firma')->assertJsonFragment(['display_name' => 'Örnek Holding A.Ş.']);

        $profile = DB::table('pms_profiles')->where('display_name', 'Yılmaz, Ahmet')->value('id');
        $before = DB::table('pms_profiles')->count();
        $this->actingAs($this->user)->post('/rezervasyon', $this->payload(['profile_id' => $profile, 'last_name' => '']))->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('pms_profiles')->count());
        $this->assertSame(2, Reservation::where('profile_id', $profile)->count());
    }

    public function test_edit_changes_dates_room_and_rate_keeping_rules(): void
    {
        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();   // +2, 4 gece, FAM, oda yok
        $room = DB::table('pms_rooms')->where('number', '402')->value('id');
        $this->actingAs($this->user)->put("/rezervasyon/{$r->id}", $this->payload([
            'arrival_date' => $r->arrival_date->format('d.m.Y'), 'departure_date' => $r->arrival_date->addDays(5)->format('d.m.Y'),
            'room_type_id' => $r->room_type_id, 'room_id' => $room, 'adults' => 2, 'children' => 2, 'manual_amount' => '7000',
        ]))->assertSessionHasNoErrors();

        $r->refresh();
        $this->assertSame(5, $r->nights);
        $this->assertSame((int) $room, (int) $r->room_id);
        $this->assertSame(5, $r->nightRows()->where('room_id', $room)->count());
        $this->assertSame(35000.0, (float) $r->total_room_amount);

        // Dolu odaya atama kuralı servisten gelir
        $occupied = DB::table('pms_rooms')->where('number', '401')->value('id');   // Müller içeride
        $this->actingAs($this->user)->put("/rezervasyon/{$r->id}", $this->payload([
            'arrival_date' => $r->arrival_date->format('d.m.Y'), 'departure_date' => $r->departure_date->format('d.m.Y'), 'room_type_id' => $r->room_type_id, 'room_id' => $occupied,
        ]))->assertSessionHasErrors('reservation');
    }

    public function test_validation_and_closed_reservations(): void
    {
        $this->actingAs($this->user)->post('/rezervasyon', $this->payload(['last_name' => '', 'departure_date' => '-1']))
            ->assertSessionHasErrors(['last_name', 'departure_date']);

        $done = Reservation::where('status', 'checked_out')->firstOrFail();
        $this->actingAs($this->user)->get("/rezervasyon/{$done->id}")->assertOk()->assertDontSee('data-hotkey="f10 ctrl+enter"', false);
    }

    public function test_permission_and_turkish_card(): void
    {
        $this->user->update(['locale' => 'tr']);
        $this->actingAs($this->user)->get('/rezervasyon/yeni')->assertSee('Gece fiyatları')->assertSee('Yemek planı')->assertSee('Yeni rezervasyon');

        $viewer = User::create(['username' => 'V', 'name' => 'V', 'email' => 'v@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        $role = DB::table('pms_roles')->insertGetId(['code' => 'viewer', 'name' => 'Görüntüleme']);
        DB::table('pms_role_permissions')->insert(['role_id' => $role, 'permission_id' => DB::table('pms_permissions')->where('code', 'reservations.view')->value('id')]);
        DB::table('pms_user_properties')->insert(['user_id' => $viewer->id, 'property_id' => $this->property, 'role_id' => $role]);
        $this->actingAs($viewer)->get('/rezervasyon')->assertOk()->assertDontSee(route('reservations.create'), false);
        $this->actingAs($viewer)->get('/rezervasyon/yeni')->assertForbidden();
    }
}
