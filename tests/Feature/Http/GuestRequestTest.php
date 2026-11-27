<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class GuestRequestTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ON', 'name' => 'Ön büro', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
    }

    public function test_request_lifecycle_with_history_and_overdue_badge(): void
    {
        $this->actingAs($this->user)->get('/istekler')->assertOk()->assertSee('Guest Requests')->assertSee('No requests.');

        $this->actingAs($this->user)->post('/istekler', ['room' => '1102', 'kind' => 'request', 'department' => 'housekeeping', 'priority' => 'high',
            'due_date' => now()->format('d.m.Y'), 'due_time' => now()->addHour()->format('H:i'), 'message' => 'Ekstra yastık'])->assertRedirect('/istekler')->assertSessionHas('status');
        $t = DB::table('pms_traces')->sole();
        $this->assertSame(['open', 'high', 'Yılmaz, Ahmet', 'App\Domain\Reservations\Models\Reservation'], [$t->status, $t->priority, $t->guest_name, $t->traceable_type]);
        $this->assertSame((int) DB::table('pms_rooms')->where('number', '1102')->value('id'), (int) $t->room_id);
        $this->actingAs($this->user)->get('/istekler')->assertSee('Ekstra yastık')->assertSee('Housekeeping');

        $this->actingAs($this->user)->post("/istekler/{$t->id}/durum", ['status' => 'in_progress', 'note' => 'Kat görevlisine iletildi'])->assertSessionHas('status');
        $this->actingAs($this->user)->post("/istekler/{$t->id}/not", ['note' => 'Misafir aradı'])->assertSessionHas('status');
        $this->actingAs($this->user)->post("/istekler/{$t->id}/durum", ['status' => 'done'])->assertSessionHas('status');
        $t = DB::table('pms_traces')->where('id', $t->id)->first();
        $this->assertSame(['done', 1], [$t->status, (int) $t->is_resolved]);
        $this->assertSame(['open', 'in_progress', null, 'done'], DB::table('pms_trace_logs')->where('trace_id', $t->id)->orderBy('id')->pluck('status')->all());
        $this->actingAs($this->user)->get("/istekler/{$t->id}")->assertOk()->assertSee('Kat görevlisine iletildi')->assertSee('Misafir aradı');
        $this->actingAs($this->user)->post("/istekler/{$t->id}/durum", ['status' => 'in_progress'])->assertSessionHas('error');   // kapalı

        // Geciken şikâyet: üst çubukta uyarı ve "Gecikmiş" sekmesi
        $this->actingAs($this->user)->post('/istekler', ['kind' => 'complaint', 'department' => 'technical', 'priority' => 'normal', 'guest_name' => 'Lobi',
            'due_date' => now()->subDay()->format('d.m.Y'), 'due_time' => '10:00', 'message' => 'Klima çalışmıyor'])->assertSessionHas('status');
        $this->actingAs($this->user)->get('/')->assertSee('data-requests-badge', false)->assertSee('1 late requests');
        $this->actingAs($this->user)->get('/istekler?sekme=overdue')->assertSee('Klima çalışmıyor')->assertDontSee('Ekstra yastık');

        $this->actingAs($this->user)->post('/istekler', ['room' => '9999', 'kind' => 'request', 'department' => 'fnb', 'priority' => 'normal', 'due_time' => '10:00', 'message' => 'x'])->assertSessionHas('error');
        $this->actingAs($this->user)->post('/istekler', ['kind' => 'request', 'department' => 'fnb', 'priority' => 'normal', 'due_time' => '10:00'])->assertSessionHasErrors('message');
    }

    public function test_permission(): void
    {
        $acc = User::create(['username' => 'MUH', 'name' => 'Muhasebe', 'email' => 'm@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $acc->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'ACC')->value('id')]);
        $this->actingAs($acc)->get('/istekler')->assertForbidden();
    }
}
