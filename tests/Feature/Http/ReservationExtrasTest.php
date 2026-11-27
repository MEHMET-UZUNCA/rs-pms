<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class ReservationExtrasTest extends DomainTestCase
{
    private User $user;

    private Reservation $res;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
        $this->res = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();   // +2, 4 gece, ESU, oda yok
    }

    private function save(array $extra): \Illuminate\Testing\TestResponse
    {
        $r = $this->res;

        return $this->actingAs($this->user)->put("/rezervasyon/{$r->id}", array_merge([
            'arrival_date' => $r->arrival_date->format('d.m.Y'), 'departure_date' => $r->departure_date->format('d.m.Y'), 'adults' => $r->adults, 'children' => $r->children,
            'room_type_id' => $r->room_type_id, 'rate_code_id' => $r->rate_code_id, 'reservation_type_id' => $r->reservation_type_id,
            'market_segment_id' => $r->market_segment_id, 'source_code_id' => $r->source_code_id, 'guarantee_type' => $r->guarantee_type ?? 'none',
        ], $extra));
    }

    public function test_features_and_matching_rooms(): void
    {
        $feature = DB::table('pms_room_features')->where('property_id', $this->property)->first();
        $rooms = DB::table('pms_rooms')->where('room_type_id', $this->res->room_type_id)->pluck('id');
        DB::table('pms_room_feature_room')->where('room_feature_id', $feature->id)->whereIn('room_id', $rooms)->delete();
        DB::table('pms_room_feature_room')->insert(['room_id' => $rooms[0], 'room_feature_id' => $feature->id]);

        $this->save(['features_sent' => '1', 'features' => [$feature->id]])->assertSessionHasNoErrors();
        $this->assertSame([(int) $feature->id], DB::table('pms_reservation_features')->where('reservation_id', $this->res->id)->pluck('room_feature_id')->map(fn ($v) => (int) $v)->all());
        $html = $this->actingAs($this->user)->get("/rezervasyon/{$this->res->id}")->assertOk()->getContent();
        $this->assertStringContainsString('data-pick-room="'.$rooms[0].'"', $html);
        $this->assertSame(1, substr_count($html, 'data-pick-room="'));   // yalnız özelliğe sahip oda

        // Features sekmesi gönderilmeden kaydedilen form tercihleri silmez; boş gönderilirse siler
        $this->save([])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('pms_reservation_features')->where('reservation_id', $this->res->id)->count());
        $this->save(['features_sent' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('pms_reservation_features')->where('reservation_id', $this->res->id)->count());
    }

    public function test_guarantee_card_keeps_only_last_four_digits(): void
    {
        $this->save(['card_last4' => '4111111111111111'])->assertSessionHasErrors('card_last4');
        $this->save(['card_holder' => 'CAN ARSLAN', 'card_brand' => 'visa', 'card_last4' => '1234', 'card_expiry' => '09/28'])->assertSessionHasNoErrors();
        $token = DB::table('pms_payment_tokens')->where('id', $this->res->fresh()->payment_token_id)->first();
        $this->assertSame(['visa', '1234', 9, 2028, 'CAN ARSLAN', 'manual'], [$token->brand, $token->last4, (int) $token->expiry_month, (int) $token->expiry_year, $token->holder_name, $token->provider]);
        $this->assertStringNotContainsString('1234', $token->token);   // şifreli
        $this->actingAs($this->user)->get("/rezervasyon/{$this->res->id}")->assertSee('•••• 1234');

        $this->save(['card_remove' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($this->res->fresh()->payment_token_id);
        $this->assertSame(0, DB::table('pms_payment_tokens')->count());
    }

    public function test_inventory_items_with_stock_check(): void
    {
        $this->actingAs($this->user)->get('/ayarlar/envanter')->assertOk()->assertSee('Inventory Items');
        $this->actingAs($this->user)->post('/ayarlar/envanter', ['code' => 'baby', 'name' => 'Bebek yatağı', 'quantity_total' => 1])->assertSessionHas('status');
        $item = DB::table('pms_items')->where('code', 'BABY')->first();
        $this->actingAs($this->user)->get("/rezervasyon/{$this->res->id}")->assertSee('BABY Bebek yatağı (1)');

        $add = fn (Reservation $r) => $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/envanter", ['item_id' => $item->id, 'quantity' => 1,
            'date_from' => $r->arrival_date->format('d.m.Y'), 'date_to' => $r->departure_date->format('d.m.Y')]);
        $add($this->res)->assertSessionHas('status');
        $other = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Aydın'))->firstOrFail();   // +3..+5, çakışır
        $add($other)->assertSessionHas('error');   // tek bebek yatağı dolu
        $this->actingAs($this->user)->post("/rezervasyon/{$this->res->id}/envanter", ['item_id' => $item->id, 'quantity' => 1, 'date_from' => '01.01.2027', 'date_to' => '03.01.2027'])
            ->assertSessionHas('error');   // konaklama dışında
        $this->actingAs($this->user)->delete('/ayarlar/envanter/'.$item->id)->assertSessionHas('error');   // kullanımda

        $ri = DB::table('pms_reservation_items')->where('reservation_id', $this->res->id)->value('id');
        $this->actingAs($this->user)->delete("/rezervasyon/{$this->res->id}/envanter/{$ri}")->assertSessionHas('status');
        $add($other)->assertSessionHas('status');
    }

    public function test_packages_tab_shows_board_elements(): void
    {
        $html = $this->actingAs($this->user)->get("/rezervasyon/{$this->res->id}")->getContent();
        $this->assertStringNotContainsString('This tab is not available yet.', $html);
        $this->assertTrue(str_contains($html, 'The rate code has no package (room only).') || str_contains($html, 'included in the rate'));
    }
}
