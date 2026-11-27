<?php

namespace Tests\Feature\Http;

use App\Domain\Reservations\Models\Reservation;
use App\Mail\ConfirmationLetterMail;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Domain\DomainTestCase;

class ConfirmationLetterTest extends DomainTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoDataSeeder::class);
        $this->user = User::create(['username' => 'ADMIN', 'name' => 'Admin', 'email' => 'admin@rocks.test', 'password' => 'gizli-sifre', 'is_active' => true]);
        DB::table('pms_user_properties')->insert(['user_id' => $this->user->id, 'property_id' => $this->property,
            'role_id' => DB::table('pms_roles')->where('code', 'admin')->value('id')]);
    }

    private function guestReservation(string $lastName): Reservation
    {
        return Reservation::whereHas('profile', fn ($q) => $q->where('last_name', $lastName))->with(['profile', 'roomType'])->firstOrFail();
    }

    public function test_english_letter_is_filled_from_reservation(): void
    {
        $r = $this->guestReservation('Johnson');
        $r->update(['guest_comments' => 'Late arrival, sea view please']);

        $html = $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu?dil=en")->assertOk()->getContent();

        foreach (['Reservation', 'Confirmation Letter', 'Dear ', 'Guest Name', 'Reservation Number', 'Check-in Date', 'Check-out Date',
            'Length of Stay', 'Room Type', 'Number of Guests', 'Room Rate', '(Per Night)', 'Booked By', 'Special Requests',
            'Check-in Time: 14:00 | Check-out Time: 12:00', 'guestrelations@rockshotel.com', 'Kind Regards,', 'Reservation Department',
            'Rocks Hotel &amp; Casino – Girne', '20 Temmuz Kordonboyu Cad. Girne / K.K.T.C.', 'img/letter/header.png', 'img/letter/signature.jpg'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString($r->confirmation_number, $html);
        $this->assertStringContainsString($r->arrival_date->format('d.m.Y'), $html);
        $this->assertStringContainsString($r->departure_date->format('d.m.Y'), $html);
        $this->assertStringContainsString(e($r->roomType->name), $html);
        $this->assertStringContainsString($r->nights.' night', $html);
        $this->assertStringContainsString('Late arrival, sea view please', $html);
        $this->assertStringContainsString($r->profile->last_name, $html);
        $this->assertStringContainsString($r->currency_code, $html);
    }

    public function test_turkish_letter_and_profile_language_default(): void
    {
        $r = $this->guestReservation('Öztürk');

        $html = $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu?dil=tr")->assertOk()->getContent();
        foreach (['Sayın ', 'Misafir Adı', 'Rezervasyon Numarası', 'Giriş Tarihi', 'Çıkış Tarihi', 'Konaklama Süresi', 'Kişi Sayısı',
            'Gecelik Oda Fiyatı', 'Rezervasyonu Yapan', 'Özel İstekler', 'Saygılarımızla,', 'Rezervasyon Departmanı', $r->nights.' gece'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // Dil verilmezse misafir profilinin dili; Türkçe değilse İngilizce
        $r->profile->update(['language_code' => 'tr']);
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertOk()->assertSee('Sayın ', false);
        $r->profile->update(['language_code' => 'de']);
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertOk()->assertSee('Dear ', false);
    }

    public function test_booked_by_shows_agent_or_company(): void
    {
        $r = $this->guestReservation('Smith');
        $agent = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'travel_agent', 'display_name' => 'Kıbrıs Tur Acentası',
            'created_at' => now(), 'updated_at' => now()]);
        $r->update(['agent_profile_id' => $agent]);

        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertOk()->assertSee('Kıbrıs Tur Acentası');
    }

    public function test_navigator_and_card_link_to_letter(): void
    {
        $r = $this->guestReservation('Johnson');
        $url = route('reservations.letter', $r);

        $this->actingAs($this->user)->get('/rezervasyon?no='.$r->confirmation_number)->assertOk()->assertSee('data-url-letter="'.$url.'"', false)->assertSee('data-action="letter"', false);
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}")->assertOk()->assertSee('href="'.$url.'"', false);
    }

    public function test_letter_of_other_property_is_not_found(): void
    {
        $r = $this->guestReservation('Johnson');
        $other = DB::table('pms_properties')->insertGetId(['code' => 'OTH', 'name' => 'Other Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'current_business_date' => '2026-12-01']);
        DB::table('pms_reservations')->where('id', $r->id)->update(['property_id' => $other]);

        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertNotFound();
    }

    public function test_letter_is_sent_by_email_and_logged(): void
    {
        Mail::fake();
        $r = $this->guestReservation('Johnson');
        DB::table('pms_profile_communications')->insert(['profile_id' => $r->profile_id, 'type' => 'email', 'value' => 'mark@example.com', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertOk()
            ->assertSee('value="mark@example.com"', false)->assertSee('Send by e-mail');

        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/onay-mektubu", ['email' => 'mark@example.com', 'dil' => 'tr'])
            ->assertRedirect(route('reservations.letter', [$r, 'dil' => 'tr']))->assertSessionHas('status');

        Mail::assertSent(ConfirmationLetterMail::class, function (ConfirmationLetterMail $mail) use ($r) {
            $html = $mail->render();

            return $mail->hasTo('mark@example.com') && $mail->letter['lang'] === 'tr'
                && str_contains($html, 'Rezervasyon Numarası') && str_contains($html, $r->confirmation_number)
                && $mail->envelope()->subject === 'Rezervasyon Onayı – Rocks Hotel & Casino ('.$r->confirmation_number.')';
        });
        $log = DB::table('pms_reservation_letters')->where('reservation_id', $r->id)->first();
        $this->assertSame(['mark@example.com', 'tr', $this->user->id], [$log->email, $log->language, (int) $log->sent_by]);
        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertSee('Last sent');
    }

    public function test_email_is_not_sent_without_smtp_settings_or_with_bad_address(): void
    {
        Mail::fake();
        $r = $this->guestReservation('Johnson');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '', 'mail.from.address' => '']);

        $this->actingAs($this->user)->get("/rezervasyon/{$r->id}/onay-mektubu")->assertOk()->assertSee('E-mail is not set up yet', false);
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/onay-mektubu", ['email' => 'mark@example.com', 'dil' => 'en'])
            ->assertSessionHas('error', 'E-mail is not set up yet (SMTP settings in .env).');

        config(['mail.mailers.smtp.host' => 'smtp.example.com', 'mail.from.address' => 'reservation@example.com']);
        $this->actingAs($this->user)->post("/rezervasyon/{$r->id}/onay-mektubu", ['email' => 'not-an-email', 'dil' => 'en'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('pms_reservation_letters')->count());
    }
}
