<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class AvailabilityScreenTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ON', 'name' => 'Ön büro', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
    }

    public function test_availability_by_room_type_and_day(): void
    {
        $html = $this->actingAs($this->user)->get('/musaitlik')->assertOk()->getContent();
        $this->assertStringContainsString('Availability', $html);
        $this->assertStringContainsString('<br>14.12', $html);
        $this->assertStringNotContainsString('<br>15.12', $html);

        $ymf = DB::table('pms_room_types')->where('property_id', $this->property)->where('code', 'YMF')->first();
        $physical = DB::table('pms_rooms')->where('room_type_id', $ymf->id)->where('is_active', true)->where('is_pseudo', false)->count();
        $sold = DB::table('pms_reservation_nights as n')->join('pms_reservations as r', 'r.id', '=', 'n.reservation_id')->where('n.room_type_id', $ymf->id)
            ->where('n.stay_date', '2026-12-01')->whereIn('r.status', ['confirmed', 'in_house'])->count();
        $this->assertGreaterThan(0, $sold);
        $this->assertMatchesRegularExpression('/data-type="YMF" href="[^"]*tip='.$ymf->id.'[^"]*">'.($physical - $sold).'</', $html);

        // Tıklayınca o tip ve tarihle yeni rezervasyon
        $card = $this->actingAs($this->user)->get('/rezervasyon/yeni?tip='.$ymf->id.'&gelis=05.12.2026&cikis=06.12.2026')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$ymf->id.'"[^>]*selected/', $card);
        $this->assertStringContainsString('value="05.12.2026"', $card);

        $this->actingAs($this->user)->get('/musaitlik?gun=7&bas=%2B7')->assertOk()->assertSee('<br>08.12', false)->assertDontSee('<br>15.12', false);
        $this->actingAs($this->user)->get('/')->assertSee(route('availability.index'), false);
    }
}
