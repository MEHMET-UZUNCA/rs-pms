<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class AuditLogTest extends DomainTestCase
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

    public function test_reservation_changes_are_logged_with_user_and_shown_on_card(): void
    {
        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();
        $this->actingAs($this->user)->put("/rezervasyon/{$r->id}", [
            'arrival_date' => $r->arrival_date->format('d.m.Y'), 'departure_date' => $r->departure_date->format('d.m.Y'), 'adults' => $r->adults, 'children' => $r->children,
            'room_type_id' => $r->room_type_id, 'rate_code_id' => $r->rate_code_id, 'reservation_type_id' => $r->reservation_type_id,
            'market_segment_id' => $r->market_segment_id, 'source_code_id' => $r->source_code_id, 'guarantee_type' => 'none', 'guest_comments' => 'Havaalanı transferi',
        ])->assertSessionHasNoErrors();
        $log = DB::table('pms_audit_logs')->where('auditable_type', 'reservation')->where('auditable_id', $r->id)->where('action', 'updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $this->user->id, (int) $log->user_id);
        $this->assertSame('Havaalanı transferi', json_decode($log->new_values, true)['guest_comments']);
        $this->assertArrayNotHasKey('updated_at', json_decode($log->new_values, true));
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}")->assertSee('data-card-changes', false)->assertSee('Havaalanı transferi');

        // İptal: durum değişikliği
        $reason = DB::table('pms_reason_codes')->where('category', 'cancellation')->value('id');
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/iptal", ['reason_code_id' => $reason]);
        $this->assertTrue(DB::table('pms_audit_logs')->where('auditable_id', $r->id)->where('auditable_type', 'reservation')->where('action', 'status_changed')->exists());
    }

    public function test_role_permission_and_user_changes_are_logged_and_listed(): void
    {
        $role = (int) DB::table('pms_roles')->where('code', 'HKS')->value('id');
        $ids = DB::table('pms_permissions')->whereIn('code', ['housekeeping.view', 'cashiering.billing'])->pluck('id')->all();
        $this->actingAs($this->user)->put("/ayarlar/roller/{$role}", ['code' => 'HKS', 'name' => 'Housekeeping Supervisor', 'permissions' => $ids])->assertSessionHasNoErrors();
        $log = DB::table('pms_audit_logs')->where('auditable_type', 'role')->where('action', 'permissions_changed')->sole();
        $this->assertContains('cashiering.billing', json_decode($log->new_values, true)['added']);

        $this->actingAs($this->user)->post('/ayarlar/kullanicilar', ['username' => 'ayse', 'name' => 'Ayşe', 'locale' => 'tr', 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id'), 'is_active' => '1']);
        $ayse = User::where('username', 'AYSE')->firstOrFail();
        $this->actingAs($this->user)->post("/ayarlar/kullanicilar/{$ayse->id}/sifre");
        $this->assertTrue(DB::table('pms_audit_logs')->where('auditable_type', 'user')->where('auditable_id', $ayse->id)->where('action', 'password_reset')->exists());
        $this->assertNull(DB::table('pms_audit_logs')->where('auditable_type', 'user')->where('new_values', 'like', '%$2y$%')->value('id'));   // şifre özeti yazılmaz

        $this->actingAs($this->user)->get('/ayarlar/degisiklik-kaydi')->assertOk()->assertSee('Audit Log')->assertSee('Permissions changed')->assertSee('Password reset');
        $this->actingAs($this->user)->get('/ayarlar/degisiklik-kaydi?tur=role')->assertSee('Permissions changed')->assertDontSee('Password reset');
        $clerk = User::create(['username' => 'ON', 'name' => 'Ön', 'email' => 'on@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'FOA')->value('id')]);
        $this->actingAs($clerk)->get('/ayarlar/degisiklik-kaydi')->assertForbidden();
    }
}
