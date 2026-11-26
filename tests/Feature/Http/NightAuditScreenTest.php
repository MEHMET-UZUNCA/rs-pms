<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class NightAuditScreenTest extends DomainTestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026: Cohen ve Smith bugün çıkmalı, 4 gelen henüz gelmedi
        $this->manager = $this->user('MUDUR', 'admin');
    }

    private function user(string $name, string $role, array $permissions = []): User
    {
        $user = User::create(['username' => $name, 'name' => $name, 'email' => strtolower($name).'@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        $roleId = DB::table('pms_roles')->where('code', $role)->value('id') ?? DB::table('pms_roles')->insertGetId(['code' => $role, 'name' => $role]);
        foreach ($permissions as $p) {
            DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => DB::table('pms_permissions')->where('code', $p)->value('id')]);
        }
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => $roleId]);

        return $user;
    }

    public function test_checklist_shows_blocking_departures_and_warning_arrivals(): void
    {
        $html = $this->actingAs($this->manager)->get('/gece-kapanisi')->assertOk()->getContent();
        $this->assertStringContainsString('Closing business date 01.12.2026', $html);
        $this->assertStringContainsString('Departures not checked out', $html);
        $this->assertStringContainsString('Cohen, David', $html);
        $this->assertStringContainsString('Smith, John', $html);
        $this->assertStringContainsString('Expected arrivals not checked in', $html);
        $this->assertStringContainsString('Petrov, Sergei', $html);
        $this->assertStringContainsString('name="override[departures_pending]"', $html);
        $this->assertStringContainsString('Start Night Audit', $html);
    }

    public function test_blocked_without_override_then_completes_with_supervisor_reason(): void
    {
        $this->actingAs($this->manager)->post('/gece-kapanisi')->assertSessionHas('error');
        $this->assertSame('2026-12-01', DB::table('pms_properties')->where('id', $this->property)->value('current_business_date'));
        $this->actingAs($this->manager)->get('/gece-kapanisi')->assertSee('Continue Night Audit')->assertSee('data-status="failed"', false);

        $this->actingAs($this->manager)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'Misafirler sabah çıkacak']])
            ->assertSessionHas('status', 'Business date 01.12.2026 closed. New business date: 02.12.2026.');

        $this->assertSame('2026-12-02', substr(DB::table('pms_properties')->where('id', $this->property)->value('current_business_date'), 0, 10));
        $this->assertSame(4, Reservation::where('status', 'no_show')->count());
        $this->assertStringContainsString('Misafirler sabah çıkacak — MUDUR', DB::table('pms_night_audit_checks')->where('code', 'departures_pending')->value('acknowledged_reason'));

        $html = $this->actingAs($this->manager)->get('/gece-kapanisi')->assertOk()->getContent();
        $this->assertStringContainsString('Last closed day 01.12.2026', $html);
        $this->assertStringContainsString('Revenue by department code', $html);
        $this->assertStringContainsString('Completed', $html);
        $this->assertStringContainsString('Closing business date 02.12.2026', $html);
    }

    public function test_permissions(): void
    {
        $clerk = $this->user('GECE', 'night', ['night_audit.view', 'night_audit.run']);
        $html = $this->actingAs($clerk)->get('/gece-kapanisi')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="override[', $html);   // amir onayı yetkisi yok
        $this->actingAs($clerk)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'x']])->assertForbidden();

        $viewer = $this->user('IZLE', 'audit_view', ['night_audit.view']);
        $this->actingAs($viewer)->get('/gece-kapanisi')->assertOk()->assertDontSee('Start Night Audit');
        $this->actingAs($viewer)->post('/gece-kapanisi')->assertForbidden();
    }
}
