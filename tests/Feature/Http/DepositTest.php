<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class DepositTest extends DomainTestCase
{
    public function test_deposit_request_paid_by_deposit_payment_and_overdue_view(): void
    {
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'a@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
        $r = Reservation::whereHas('profile', fn ($q) => $q->where('last_name', 'Arslan'))->firstOrFail();   // +2

        $this->actingAs($user)->post("/rezervasyon/{$r->id}/depozito", ['due_date' => '30.11.2026', 'amount' => '5000', 'currency_code' => 'TRY'])->assertSessionHas('status');
        $this->actingAs($user)->post("/rezervasyon/{$r->id}/depozito", ['due_date' => '10.12.2026', 'amount' => '2500', 'currency_code' => 'TRY'])->assertSessionHas('status');
        $r->refresh();
        $this->assertSame([7500.0, '2026-11-30'], [(float) $r->deposit_required, $r->deposit_due_date->toDateString()]);
        $this->actingAs($user)->get("/rezervasyon/{$r->id}")->assertSee('Deposit requests')->assertSee('overdue');
        $this->actingAs($user)->get('/rezervasyon?f=depozito')->assertSee('Arslan, Can');

        // Depozito koduyla ödeme: en eski istek ödendi
        $code = DB::table('pms_transaction_codes')->where('property_id', $this->property)->where('code', '992')->value('id');
        $this->actingAs($user)->post('/folyo/odeme', ['reservation_id' => $r->id, 'window' => 1, 'payment_code_id' => $code, 'amount' => '5000'])->assertSessionHas('status');
        $this->assertSame(['paid', 'pending'], DB::table('pms_deposit_requests')->where('reservation_id', $r->id)->orderBy('due_date')->pluck('status')->all());
        $this->assertNotNull(DB::table('pms_deposit_requests')->where('reservation_id', $r->id)->where('status', 'paid')->value('paid_posting_id'));
        $r->refresh();
        $this->assertSame([2500.0, '2026-12-10'], [(float) $r->deposit_required, $r->deposit_due_date->toDateString()]);
        $this->actingAs($user)->get('/rezervasyon?f=depozito')->assertDontSee('Arslan, Can');

        // Elle iptal
        $second = DB::table('pms_deposit_requests')->where('reservation_id', $r->id)->where('status', 'pending')->value('id');
        $this->actingAs($user)->post("/rezervasyon/{$r->id}/depozito/{$second}", ['status' => 'cancelled'])->assertSessionHas('status');
        $this->assertSame(0.0, (float) $r->fresh()->deposit_required);
        $this->assertTrue(DB::table('pms_audit_logs')->where('auditable_id', $r->id)->where('action', 'deposit_requested')->exists());
    }
}
