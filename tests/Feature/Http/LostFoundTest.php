<?php

namespace Tests\Feature\Http;

use App\Domain\Housekeeping\Models\LostFoundItem;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

class LostFoundTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);   // iş günü 01.12.2026
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function reason(string $category, string $code): int
    {
        return (int) DB::table('pms_reason_codes')->where('property_id', $this->property)->where('category', $category)->where('code', $code)->value('id');
    }

    private function add(string $kind, array $data = []): LostFoundItem
    {
        $this->actingAs($this->user)->post("/kayip-esya/yeni/{$kind}", $data + ['description' => 'Black iPhone 14', 'found_date' => 't', 'found_time' => '10:30',
            'group_id' => $this->reason('lost_found_group', 'ELEC')])->assertRedirect('/kayip-esya')->assertSessionHasNoErrors();

        return LostFoundItem::where('kind', $kind)->latest('id')->firstOrFail();
    }

    public function test_screen_and_new_lost_and_found_items(): void
    {
        $this->actingAs($this->user)->get('/kayip-esya')->assertOk()->assertSee('Lost Items')->assertSee('Found Items')->assertSee('Include matched')->assertSee('Elektronik / Electronics');

        $lost = $this->add('lost', ['person_name' => 'Mark Johnson', 'contact' => '+44 7700 900000', 'room' => '1102']);
        $found = $this->add('found', ['public_place_id' => $this->reason('public_place', 'CASINO'), 'location' => 'Table 4', 'storage_location' => 'Safe 2']);

        $this->assertSame(['L000001', 'open', '1102', '2026-12-01 10:30'], [$lost->number, $lost->status, $lost->room->number, $lost->found_at->format('Y-m-d H:i')]);
        $this->assertSame(['F000001', $this->user->id], [$found->number, (int) $found->found_by]);
        $this->assertSame('reported', DB::table('pms_lost_found_actions')->where('lost_found_id', $lost->id)->value('action'));

        $html = $this->actingAs($this->user)->get('/kayip-esya')->getContent();
        $this->assertStringContainsString('L000001', $html);
        $this->assertStringContainsString('Table 4', $html);
        $this->actingAs($this->user)->get('/kayip-esya?l_item=iphone')->assertSee('L000001');
        $this->actingAs($this->user)->get('/kayip-esya?l_item=wallet')->assertDontSee('L000001');
        $this->actingAs($this->user)->get("/kayip-esya/{$lost->id}")->assertOk()->assertSee('Mark Johnson')->assertSee('Reported');

        $this->actingAs($this->user)->post('/kayip-esya/yeni/lost', ['description' => '', 'found_date' => 't'])->assertSessionHasErrors('description');
        $this->actingAs($this->user)->post('/kayip-esya/yeni/lost', ['description' => 'x', 'found_date' => 't', 'room' => '9999'])->assertSessionHasErrors('room');
    }

    public function test_match_return_and_history(): void
    {
        $lost = $this->add('lost');
        $found = $this->add('found');

        $this->actingAs($this->user)->post('/kayip-esya/eslestir', ['lost_id' => $lost->id, 'found_id' => $found->id])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(['matched', $found->id], [$lost->fresh()->status, (int) $lost->fresh()->matched_id]);
        $row = 'data-url-edit="'.route('lost-found.edit', $lost).'"';
        $this->actingAs($this->user)->get('/kayip-esya')->assertDontSee($row, false);                // eşleşenler varsayılan olarak gizli
        $this->actingAs($this->user)->get('/kayip-esya?l_matched=1')->assertSee($row, false);
        $this->actingAs($this->user)->post('/kayip-esya/eslestir', ['lost_id' => $found->id, 'found_id' => $lost->id])->assertSessionHas('error');   // tür yanlış

        $this->actingAs($this->user)->post("/kayip-esya/{$lost->id}/kapat", ['how' => 'returned'])->assertSessionHasErrors('to');
        $this->actingAs($this->user)->post("/kayip-esya/{$lost->id}/kapat", ['how' => 'returned', 'to' => 'Mark Johnson (passport)'])->assertSessionHas('status');
        $this->assertSame(['returned', 'returned'], [$lost->fresh()->status, $found->fresh()->status]);   // eşleşen de kapanır
        $this->assertSame('Mark Johnson (passport)', $found->fresh()->returned_to);

        $this->actingAs($this->user)->put("/kayip-esya/{$lost->id}", ['description' => 'x', 'found_date' => 't'])->assertSessionHas('error');   // kapanmış düzenlenmez
        $this->actingAs($this->user)->post("/kayip-esya/{$lost->id}/not", ['note' => 'Guest called to thank'])->assertSessionHas('status');
        $this->assertSame(['reported', 'matched', 'returned', 'note'], DB::table('pms_lost_found_actions')->where('lost_found_id', $lost->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_unmatch_dispose_and_delete(): void
    {
        $lost = $this->add('lost');
        $found = $this->add('found');
        $this->actingAs($this->user)->post('/kayip-esya/eslestir', ['lost_id' => $lost->id, 'found_id' => $found->id]);
        $this->actingAs($this->user)->delete("/kayip-esya/{$found->id}")->assertSessionHas('error');   // eşleşmiş silinmez
        $this->actingAs($this->user)->post("/kayip-esya/{$found->id}/ayir")->assertSessionHas('status');
        $this->assertSame(['open', 'open'], [$lost->fresh()->status, $found->fresh()->status]);

        $this->actingAs($this->user)->post("/kayip-esya/{$found->id}/kapat", ['how' => 'disposed', 'note' => 'Donated after 90 days'])->assertSessionHas('status');
        $this->assertSame('disposed', $found->fresh()->status);
        $this->actingAs($this->user)->delete("/kayip-esya/{$lost->id}")->assertRedirect('/kayip-esya');
        $this->assertNull(LostFoundItem::find($lost->id));
    }

    public function test_permission_and_menu(): void
    {
        $this->actingAs($this->user)->get('/')->assertSee(route('lost-found.index'), false)->assertDontSee('Ctrl+M');
        $clerk = User::create(['username' => 'X', 'name' => 'X', 'email' => 'x@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $clerk->id, 'property_id' => $this->property, 'role_id' => DB::table('pms_roles')->where('code', 'ACC')->value('id')]);
        $this->actingAs($clerk)->get('/kayip-esya')->assertForbidden();
    }
}
