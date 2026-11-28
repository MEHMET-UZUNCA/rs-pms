<?php

namespace Tests\Feature\Http;

use App\Domain\Cashiering\Services\FolioService;
use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
use App\Domain\Reservations\Models\Block;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\BlockService;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class BlockScreenTest extends DomainTestCase
{
    private User $user;

    private Carbon $bd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
        $this->bd = Carbon::parse(DB::table('pms_properties')->where('id', $this->property)->value('current_business_date'));
        DB::table('pms_rate_amounts')->insert(['rate_code_id' => $this->id('pms_rate_codes', 'BAR'), 'room_type_id' => $this->id('pms_room_types', 'YMF'),
            'date_from' => $this->bd->toDateString(), 'date_to' => $this->bd->copy()->addDays(30)->toDateString(), 'price_2' => 5000]);
    }

    private function id(string $table, string $code): int
    {
        return (int) DB::table($table)->where('property_id', $this->property)->where('code', $code)->value('id');
    }

    private function createBlock(array $override = []): Block
    {
        $agent = DB::table('pms_profiles')->where('property_id', $this->property)->where('type', 'travel_agent')->value('id')
            ?? DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'travel_agent', 'display_name' => 'Kıbrıs Tur', 'company_name' => 'Kıbrıs Tur']);
        $this->actingAs($this->user)->post('/bloklar', array_merge([
            'code' => 'chess27', 'name' => 'Satranç Turnuvası', 'type' => 'group', 'status' => 'definite', 'profile_id' => $agent,
            'start_date' => $this->bd->format('d.m.Y'), 'end_date' => $this->bd->copy()->addDays(2)->format('d.m.Y'), 'cutoff_date' => '',
            'rate_code_id' => $this->id('pms_rate_codes', 'BAR'), 'market_segment_id' => $this->id('pms_market_segments', 'IND'), 'source_code_id' => $this->id('pms_source_codes', 'DIR'),
            'rooms' => [$this->id('pms_room_types', 'YMF') => 4, $this->id('pms_room_types', 'ESU') => 0],
        ], $override))->assertSessionHasNoErrors()->assertRedirect();

        return Block::where('code', 'CHESS27')->firstOrFail();
    }

    public function test_group_block_pick_up_assign_check_in_and_master_routing(): void
    {
        $this->actingAs($this->user)->get('/bloklar/yeni?tur=group')->assertOk()->assertSee('New group');
        $block = $this->createBlock();
        $this->assertNotNull($block->group_profile_id);
        $this->assertSame(8, (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->sum('allotted'));   // 4 oda × 2 gece
        $this->actingAs($this->user)->get('/bloklar')->assertOk()->assertSee('CHESS27')->assertSee('Satranç Turnuvası');

        // Kotadan 3 rezervasyon: 2 isim + grup adıyla 1
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/rezervasyonlar", [
            'room_type_id' => $this->id('pms_room_types', 'YMF'), 'count' => 3, 'names' => "Kasparov, Garry\nJudit Polgar", 'adults' => 2,
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'arrival_date' => $this->bd->format('d.m.Y'), 'departure_date' => $this->bd->copy()->addDays(2)->format('d.m.Y'),
        ]);
        $this->assertNull(session('error'), (string) session('error'));
        $members = Reservation::where('block_id', $block->id)->with('profile')->orderBy('id')->get();
        $this->assertSame(['Kasparov, Garry', 'Polgar, Judit', 'Satranç Turnuvası'], $members->pluck('profile.display_name')->all());
        $this->assertTrue($members->every(fn ($r) => (int) $r->group_profile_id === (int) $block->group_profile_id && $r->status === 'confirmed' && $r->agent_profile_id));
        $this->assertSame(1, (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->min(DB::raw('allotted - picked_up')));

        // Kotadan fazlası açılamaz
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/rezervasyonlar", ['room_type_id' => $this->id('pms_room_types', 'YMF'), 'count' => 2, 'adults' => 2,
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'arrival_date' => $this->bd->format('d.m.Y'), 'departure_date' => $this->bd->copy()->addDays(2)->format('d.m.Y')])
            ->assertSessionHas('error');

        $html = $this->actingAs($this->user)->get("/bloklar/{$block->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Kasparov, Garry', $html);
        $this->assertMatchesRegularExpression('/data-remaining="YMF">1</', $html);

        // Oda ata, toplu giriş
        $ids = $members->pluck('id')->all();
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'assign', 'ids' => $ids])->assertSessionHas('status');
        $rooms = Reservation::whereIn('id', $ids)->pluck('room_id');
        $this->assertCount(3, $rooms->filter()->unique());
        DB::table('pms_rooms')->whereIn('id', $rooms)->update(['hk_status' => 'inspected']);
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'check_in', 'ids' => $ids])->assertSessionHas('status')->assertSessionMissing('error');
        $this->assertSame(3, Reservation::whereIn('id', $ids)->where('status', 'in_house')->count());

        // Ana hesaba yönlendir (2 üye); gece kapanışında oda + pansiyon ana hesaba, üçüncü üye kendi folyosuna
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'route', 'ids' => array_slice($ids, 0, 2)])->assertSessionHas('status');
        $master = DB::table('pms_folios')->where('id', $block->fresh()->master_folio_id)->first();
        $this->assertSame(['master', 'Satranç Turnuvası'], [$master->type, $master->name]);
        $this->actingAs($this->user)->get("/bloklar/{$block->id}")->assertSee($master->folio_number);

        NightAuditAnswers::save($this->property, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'continue'), null);
        DB::table('pms_reservations')->where('property_id', $this->property)->where('status', 'in_house')->where('departure_date', '<=', $this->bd->toDateString())
            ->update(['departure_date' => $this->bd->copy()->addDays(5)->toDateString()]);
        app(NightAuditService::class)->run($this->property, null, array_fill_keys(array_keys(NightAuditAnswers::get($this->property)), 'test'));
        $nightAmount = fn (int $id) => (float) DB::table('pms_reservation_nights')->where('reservation_id', $id)->where('stay_date', $this->bd->toDateString())->value('amount');
        $this->assertEqualsWithDelta($nightAmount($ids[0]) + $nightAmount($ids[1]), (float) DB::table('pms_folios')->where('id', $master->id)->value('balance'), 0.01);
        $own = DB::table('pms_folios')->where('reservation_id', $ids[2])->where('window', 1)->value('balance');
        $this->assertEqualsWithDelta($nightAmount($ids[2]), (float) $own, 0.01);
        $this->assertSame(0.0, (float) DB::table('pms_folios')->where('reservation_id', $ids[0])->where('window', 1)->value('balance'));

        // Yönlendirme kaldırılır
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'unroute', 'ids' => [$ids[0]]])->assertSessionHas('status');
        $this->assertSame(1, DB::table('pms_routing_rules')->where('target_folio_id', $master->id)->count());
    }

    public function test_block_validation_and_dates(): void
    {
        $block = $this->createBlock();
        $this->actingAs($this->user)->post('/bloklar', ['code' => 'chess27', 'name' => 'x', 'type' => 'group', 'status' => 'definite'])->assertSessionHasErrors(['code', 'profile_id', 'start_date']);
        $this->actingAs($this->user)->get("/bloklar/{$block->id}/duzenle")->assertOk()->assertSee('value="4"', false);

        // Rezervasyon blok tarihleri dışına taşınamaz
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/rezervasyonlar", ['room_type_id' => $this->id('pms_room_types', 'YMF'), 'count' => 1, 'adults' => 2,
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'arrival_date' => $this->bd->format('d.m.Y'), 'departure_date' => $this->bd->copy()->addDays(4)->format('d.m.Y')])
            ->assertSessionHas('error');
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'assign'])->assertSessionHasErrors('ids');
    }

    public function test_menu_and_permissions(): void
    {
        $this->actingAs($this->user)->get('/')->assertSee(route('blocks.index'), false);
        $hks = User::create(['username' => 'KAT', 'name' => 'Kat', 'email' => 'kat@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $hks->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'IFC')->value('id')]);
        $this->actingAs($hks)->get('/bloklar')->assertForbidden();
    }

    public function test_nightly_allotment_release_and_mass_check_out(): void
    {
        $ymf = $this->id('pms_room_types', 'YMF');
        $block = $this->createBlock(['end_date' => $this->bd->copy()->addDays(4)->format('d.m.Y'), 'release_days' => 2]);   // 4 gece × 4 oda
        $d = fn (int $add) => $this->bd->copy()->addDays($add)->toDateString();
        $this->actingAs($this->user)->get("/bloklar/{$block->id}")->assertOk()->assertSee('data-block-grid', false);

        // Gece gece kota: 3. gece 6 oda; başlık yeniden kaydedilince (sayı aynı) korunur
        $this->actingAs($this->user)->put("/bloklar/{$block->id}/kota", ['q' => [$ymf => [$d(2) => 6]]])->assertSessionHas('status', 'Allotment saved (1 nights changed).');
        $this->actingAs($this->user)->put("/bloklar/{$block->id}", ['code' => 'CHESS27', 'name' => 'Satranç Turnuvası', 'type' => 'group', 'status' => 'definite',
            'profile_id' => $block->profile_id, 'start_date' => $this->bd->format('d.m.Y'), 'end_date' => $this->bd->copy()->addDays(4)->format('d.m.Y'), 'release_days' => 2,
            'rate_code_id' => $block->rate_code_id, 'market_segment_id' => $block->market_segment_id, 'source_code_id' => $block->source_code_id, 'rooms' => [$ymf => 4]])->assertSessionHasNoErrors();
        $allot = fn (string $date) => (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->where('date', $date)->value('allotted');
        $this->assertSame([4, 6], [$allot($d(0)), $allot($d(2))]);

        // 2 oda alınır; kota alınan sayının altına inemez
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/rezervasyonlar", ['room_type_id' => $ymf, 'count' => 2, 'adults' => 2,
            'reservation_type_id' => $this->id('pms_reservation_types', 'GTD'), 'arrival_date' => $this->bd->format('d.m.Y'), 'departure_date' => $this->bd->copy()->addDay()->format('d.m.Y')]);
        $this->actingAs($this->user)->put("/bloklar/{$block->id}/kota", ['q' => [$ymf => [$d(0) => 1]]])->assertSessionHas('error');

        // Bırakma: 2 gün kala → bugün, +1, +2 gecelerinin alınmamış odaları bırakılır; +3 kalır
        $result = app(BlockService::class)->releaseDue($this->property, $this->bd->toDateString());
        $this->assertSame(1, $result['blocks_released']);
        $this->assertSame((4 - 2) + 4 + 6, $result['rooms_released']);
        $released = fn (string $date) => (int) DB::table('pms_block_inventory')->where('block_id', $block->id)->where('date', $date)->value('released');
        $this->assertSame([2, 4, 6, 0], [$released($d(0)), $released($d(1)), $released($d(2)), $released($d(3))]);
        $this->assertSame(0, app(BlockService::class)->releaseDue($this->property, $this->bd->toDateString())['rooms_released']);
        $this->actingAs($this->user)->get("/bloklar/{$block->id}")->assertSee('−4');

        // Toplu çıkış: bugün çıkacak, bakiyesi sıfır üye çıkar; bakiyeli olan bildirilir
        $members = Reservation::where('block_id', $block->id)->orderBy('id')->get();
        DB::table('pms_rooms')->whereIn('id', DB::table('pms_rooms')->where('room_type_id', $ymf)->whereNull('current_reservation_id')->limit(2)->pluck('id'))->update(['hk_status' => 'inspected']);
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'assign', 'ids' => $members->pluck('id')->all()]);
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'check_in', 'ids' => $members->pluck('id')->all()])->assertSessionMissing('error');
        DB::table('pms_reservations')->whereIn('id', $members->pluck('id'))->update(['departure_date' => $this->bd->toDateString()]);
        $folio = app(FolioService::class)->window($members[1]->fresh(), 1);
        DB::table('pms_folios')->where('id', $folio->id)->update(['balance' => 100]);
        $this->actingAs($this->user)->post("/bloklar/{$block->id}/islem", ['action' => 'check_out', 'ids' => $members->pluck('id')->all()])
            ->assertSessionHas('status', '1 guests checked out.')->assertSessionHas('error');
        $this->assertSame(['checked_out', 'in_house'], [$members[0]->fresh()->status, $members[1]->fresh()->status]);
    }
}
