<?php

namespace Tests\Feature\Http;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\NightAudit\Services\NightAuditService;
use App\Domain\NightAudit\Support\NightAuditAnswers;
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

    public function test_checklist_shows_fidelio_checks_all_stop_by_default(): void
    {
        $html = $this->actingAs($this->manager)->get('/gece-kapanisi')->assertOk()->getContent();
        $this->assertStringContainsString('Closing business date 01.12.2026', $html);
        $this->assertStringContainsString('Due out reservations', $html);
        $this->assertStringContainsString('Cohen, David', $html);
        $this->assertStringContainsString('Smith, John', $html);
        $this->assertStringContainsString('Expected reservations (become No Show)', $html);
        $this->assertStringContainsString('Petrov, Sergei', $html);
        $this->assertStringContainsString('name="override[departures_pending]"', $html);
        $this->assertStringContainsString('name="override[arrivals_pending]"', $html);     // Fidelio cevabı Stop → engeller
        foreach (['Answers for Automatic Night Audit', 'Due out financial accounts', 'Expected financial accounts', 'Open cashiers', 'name="answer[arrivals_pending]"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('name="answer[departures_pending]"', $html);   // her zaman Stop
        $this->assertStringContainsString('Start Night Audit', $html);
    }

    public function test_blocked_without_override_then_completes_with_supervisor_reason(): void
    {
        $this->actingAs($this->manager)->post('/gece-kapanisi')->assertSessionHas('error');
        $this->assertSame('2026-12-01', DB::table('pms_properties')->where('id', $this->property)->value('current_business_date'));
        $this->actingAs($this->manager)->get('/gece-kapanisi')->assertSee('Continue Night Audit')->assertSee('data-status="failed"', false);

        $this->actingAs($this->manager)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'Misafirler sabah çıkacak', 'arrivals_pending' => 'Gelmeyenler no-show']])
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

    public function test_answers_are_saved_and_continue_lets_audit_pass(): void
    {
        $this->actingAs($this->manager)->put('/gece-kapanisi/cevaplar', ['answer' => ['arrivals_pending' => 'continue', 'due_out_accounts' => 'continue']])
            ->assertRedirect('/gece-kapanisi')->assertSessionHas('status', 'Night audit answers saved.');
        $answers = NightAuditAnswers::get($this->property);
        $this->assertSame(['continue', 'continue', 'stop', 'stop', 'stop'], [$answers['arrivals_pending'], $answers['due_out_accounts'],
            $answers['no_show_balance'], $answers['expected_accounts'], $answers['departures_pending']]);

        $this->actingAs($this->manager)->put('/gece-kapanisi/cevaplar', ['answer' => ['arrivals_pending' => 'maybe']])->assertSessionHasErrors();

        // Gelmeyenler artık yalnız uyarı; çıkışı yapılmamış misafir hâlâ engeller
        $this->actingAs($this->manager)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'Sabah çıkacaklar']])->assertSessionHas('status');
        $this->assertSame(4, Reservation::where('status', 'no_show')->count());
    }

    public function test_financial_account_checks(): void
    {
        $fa = Folio::where('folio_number', 'FA-DEMO-1')->firstOrFail();
        $fa->update(['valid_until' => '2026-12-01']);                                  // bugün süresi doluyor
        Folio::where('folio_number', 'FA-DEMO-2')->update(['valid_from' => '2026-12-01']);   // bugün açılması bekleniyor

        $html = $this->actingAs($this->manager)->get('/gece-kapanisi')->assertOk()->getContent();
        $this->assertStringContainsString('name="override[due_out_accounts]"', $html);
        $this->assertStringContainsString('name="override[expected_accounts]"', $html);
        $this->assertStringContainsString('FA-DEMO-1', $html);
        $this->assertStringContainsString('FA-DEMO-2', $html);

        NightAuditAnswers::save($this->property, ['arrivals_pending' => 'continue', 'due_out_accounts' => 'continue', 'expected_accounts' => 'continue'], null);
        $this->actingAs($this->manager)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'x']])->assertSessionHas('status');

        $this->assertSame('2026-12-02', $fa->fresh()->valid_until->toDateString());             // bir gün uzatıldı
        $this->assertSame('2026-12-01', Folio::where('folio_number', 'FA-DEMO-2')->first()->actual_from->toDateString());   // açıldı
    }

    public function test_no_show_with_balance_is_a_separate_check(): void
    {
        $petrov = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Petrov'))->firstOrFail();
        $folio = app(FolioService::class)->window($petrov, 1);
        $folio->update(['balance' => -500]);                                           // ön ödeme alınmış

        $checks = app(NightAuditService::class)->preview($this->property);
        $this->assertTrue($checks['no_show_balance']['items']->contains('id', $petrov->id));
        $this->assertFalse($checks['arrivals_pending']['items']->contains('id', $petrov->id));

        NightAuditAnswers::save($this->property, ['arrivals_pending' => 'continue'], null);
        $this->actingAs($this->manager)->post('/gece-kapanisi', ['override' => ['departures_pending' => 'x']])->assertSessionHas('error');
        $this->assertStringContainsString('no_show_balance', (string) DB::table('pms_night_audit_steps')->where('code', 'checks')->value('error'));
    }

    public function test_answers_need_override_permission(): void
    {
        $clerk = $this->user('GECE', 'night', ['night_audit.view', 'night_audit.run']);
        $this->actingAs($clerk)->get('/gece-kapanisi')->assertOk()->assertDontSee('name="answer[', false)->assertSee('Answers for Automatic Night Audit');
        $this->actingAs($clerk)->put('/gece-kapanisi/cevaplar', ['answer' => ['arrivals_pending' => 'continue']])->assertForbidden();
    }
}
