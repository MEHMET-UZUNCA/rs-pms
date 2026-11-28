<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class RoomSetupTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function physical(int $typeId): int
    {
        $bd = DB::table('pms_properties')->where('id', $this->property)->value('current_business_date');

        return (int) DB::table('pms_inventory_daily')->where('room_type_id', $typeId)->where('date', $bd)->value('physical_rooms');
    }

    public function test_room_types_create_update_and_protected_delete(): void
    {
        $this->actingAs($this->user)->get('/ayarlar/oda-tipleri')->assertOk()->assertSee('data-room-types', false)->assertSee('YMF');
        $this->actingAs($this->user)->post('/ayarlar/oda-tipleri', ['code' => 'fam', 'name' => 'Family Suite', 'default_adults' => 2, 'max_adults' => 4,
            'max_children' => 2, 'max_occupancy' => 5, 'beds' => 3, 'is_active' => 1, 'is_bookable_online' => 1])->assertSessionHasNoErrors();
        $fam = DB::table('pms_room_types')->where('code', 'FAM')->first();
        $this->assertSame([4, 5, 1], [(int) $fam->max_adults, (int) $fam->max_occupancy, (int) $fam->is_bookable_online]);

        // en çok kişi en çok yetişkinden az olamaz
        $this->actingAs($this->user)->put("/ayarlar/oda-tipleri/{$fam->id}", ['code' => 'FAM', 'name' => 'Family', 'default_adults' => 2, 'max_adults' => 4,
            'max_children' => 0, 'max_occupancy' => 3, 'beds' => 3])->assertSessionHasErrors('max_occupancy');

        $ymf = DB::table('pms_room_types')->where('code', 'YMF')->value('id');
        $this->actingAs($this->user)->delete("/ayarlar/oda-tipleri/{$ymf}")->assertSessionHas('error');
        $this->actingAs($this->user)->delete("/ayarlar/oda-tipleri/{$fam->id}")->assertSessionHas('status');
        $this->assertFalse(DB::table('pms_room_types')->where('code', 'FAM')->exists());
    }

    public function test_rooms_create_edit_features_inactive_and_availability(): void
    {
        $ymf = (int) DB::table('pms_room_types')->where('code', 'YMF')->value('id');
        $before = $this->physical($ymf);
        $feature = DB::table('pms_room_features')->where('property_id', $this->property)->orderBy('id')->first();
        $this->actingAs($this->user)->get('/ayarlar/odalar?tip='.$ymf)->assertOk()->assertSee('room-grid', false);
        $this->actingAs($this->user)->get('/ayarlar/odalar/yeni')->assertOk()->assertSee('data-room-form', false);

        $this->actingAs($this->user)->post('/ayarlar/odalar', ['_posted' => 1, 'number' => '9001', 'room_type_id' => $ymf, 'is_active' => 1, 'is_accessible' => 1,
            'features' => [$feature->id]])->assertSessionHasNoErrors()->assertRedirect();
        $room = DB::table('pms_rooms')->where('number', '9001')->first();
        $this->assertSame([1, 1.0], [(int) $room->is_accessible, (float) $room->hk_credits]);
        $this->assertSame([(int) $feature->id], DB::table('pms_room_feature_room')->where('room_id', $room->id)->pluck('room_feature_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame($before + 1, $this->physical($ymf));
        $this->actingAs($this->user)->post('/ayarlar/odalar', ['number' => '9001', 'room_type_id' => $ymf])->assertSessionHasErrors('number');   // tekrar

        // Pasif: müsaitlikten düşer, özellikler temizlenir
        $this->actingAs($this->user)->put("/ayarlar/odalar/{$room->id}", ['_posted' => 1, 'number' => '9001', 'room_type_id' => $ymf])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->physical($ymf));
        $this->assertSame(0, DB::table('pms_room_feature_room')->where('room_id', $room->id)->count());

        // Geçmişi olmayan oda silinir; rezervasyonu olan oda silinmez, tipi değiştirilemez
        $this->actingAs($this->user)->delete("/ayarlar/odalar/{$room->id}")->assertSessionHas('status');
        $busy = DB::table('pms_reservations')->where('status', 'in_house')->whereNotNull('room_id')->value('room_id');
        $busyRoom = DB::table('pms_rooms')->where('id', $busy)->first();
        $other = DB::table('pms_room_types')->where('code', '<>', DB::table('pms_room_types')->where('id', $busyRoom->room_type_id)->value('code'))->value('id');
        $this->actingAs($this->user)->put("/ayarlar/odalar/{$busy}", ['_posted' => 1, 'number' => $busyRoom->number, 'room_type_id' => $other, 'is_active' => 1])->assertSessionHas('error');
        $this->actingAs($this->user)->delete("/ayarlar/odalar/{$busy}")->assertSessionHas('error');
    }

    public function test_permission(): void
    {
        $agent = User::create(['username' => 'FOA1', 'name' => 'Agent', 'email' => 'foa@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $agent->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($agent)->get('/ayarlar/odalar')->assertForbidden();
        $this->actingAs($agent)->get('/ayarlar/oda-tipleri')->assertForbidden();
    }
}
