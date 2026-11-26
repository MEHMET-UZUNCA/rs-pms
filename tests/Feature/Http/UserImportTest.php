<?php

namespace Tests\Feature\Http;

use App\Console\Commands\ImportFidelioUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Domain\DomainTestCase;

class UserImportTest extends DomainTestCase
{
    private string $input;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->input = tempnam(sys_get_temp_dir(), 'users');
        $this->output = tempnam(sys_get_temp_dir(), 'pw');
        file_put_contents($this->input, "# test\nRole\tInitials\tName\tTitle\tLogin\nFOA\tAAA\tAYSE ORNEK\tRESEPSIYONIST\tayse\nAUD\tNIG\tNight Auditor\tNight\tNIGHT\nXXX\tYYY\tBilinmeyen\tx\tBILINMEZ\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->input);
        @unlink($this->output);
        parent::tearDown();
    }

    private function passwordOf(string $username): string
    {
        preg_match('/^'.$username.'\s+.*Şifre: (\S+)$/m', file_get_contents($this->output), $m);

        return $m[1];
    }

    public function test_users_are_created_with_random_password_role_and_report(): void
    {
        $this->artisan('pms:import-users', ['file' => $this->input, '--output' => $this->output])->assertSuccessful();

        $ayse = User::where('username', 'AYSE')->firstOrFail();
        $this->assertSame(['Ayse Ornek', 'AAA', 'RESEPSIYONIST', true, 'en'], [$ayse->name, $ayse->initials, $ayse->title, $ayse->must_change_password, $ayse->locale]);
        $this->assertSame('FOA', DB::table('pms_user_properties as up')->join('pms_roles as r', 'r.id', '=', 'up.role_id')->where('up.user_id', $ayse->id)->value('r.code'));

        $report = file_get_contents($this->output);
        $this->assertStringContainsString('Rol: FOA — Front Office Agent', $report);
        $this->assertStringContainsString('Giriş (check-in) (reservations.checkin)', $report);
        $this->assertStringContainsString('Gece kapanışı kontrollerini amir onayıyla geçme (night_audit.override)', $report);   // NIGHT = AUD
        $this->assertStringContainsString('BILINMEZ: rol bulunamadı (XXX)', $report);
        $password = $this->passwordOf('AYSE');
        $this->assertMatchesRegularExpression('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[2-9])[A-Za-z2-9]{12}$/', $password);
        $this->assertTrue(Hash::check($password, $ayse->password));
        $this->assertNotSame($password, $this->passwordOf('NIGHT'));

        // İkinci çalıştırma var olanlara dokunmaz
        $this->artisan('pms:import-users', ['file' => $this->input, '--output' => $this->output])->assertSuccessful();
        $this->assertStringContainsString('AYSE: zaten var', file_get_contents($this->output));
        $this->assertTrue(Hash::check($password, $ayse->fresh()->password));
    }

    public function test_first_login_forces_password_change(): void
    {
        $this->artisan('pms:import-users', ['file' => $this->input, '--output' => $this->output]);
        $password = $this->passwordOf('AYSE');

        $this->post('/giris', ['login' => 'ayse', 'password' => $password])->assertRedirect('/');
        $this->get('/rezervasyon')->assertRedirect('/sifre');
        $this->get('/sifre')->assertOk()->assertSee('temporary password');

        $this->put('/sifre', ['current_password' => $password, 'password' => 'kisa', 'password_confirmation' => 'kisa'])->assertSessionHasErrors('password');
        $this->put('/sifre', ['current_password' => 'yanlis', 'password' => 'YeniSifre2026', 'password_confirmation' => 'YeniSifre2026'])->assertSessionHasErrors('current_password');
        $this->put('/sifre', ['current_password' => $password, 'password' => 'YeniSifre2026', 'password_confirmation' => 'YeniSifre2026'])->assertRedirect('/');

        $user = User::where('username', 'AYSE')->first();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->get('/rezervasyon')->assertOk();
    }

    public function test_password_generator_avoids_confusing_characters(): void
    {
        foreach (range(1, 50) as $i) {
            $this->assertDoesNotMatchRegularExpression('/[01OIl]/', ImportFidelioUsers::password());
        }
    }
}
