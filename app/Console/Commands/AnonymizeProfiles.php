<?php

namespace App\Console\Commands;

use App\Domain\Profiles\Models\Profile;
use App\Domain\Profiles\Services\ProfileAnonymizer;
use App\Domain\Reservations\Support\ReservationOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** KVKK saklama süresi: son konaklaması N yıldan eski misafir profillerini anonimleştirir (önce --dry-run ile sayıya bakın). */
class AnonymizeProfiles extends Command
{
    protected $signature = 'pms:anonymize-profiles {--years= : Saklama süresi (yıl); boşsa ayardaki değer} {--dry-run : Yalnız say, değiştirme}';

    protected $description = 'Saklama süresi dolan misafir profillerini anonimleştirir (KVKK)';

    public function handle(ProfileAnonymizer $anonymizer): int
    {
        $total = 0;
        foreach (DB::table('pms_properties')->pluck('id') as $propertyId) {
            $years = (int) ($this->option('years') ?: (ReservationOptions::get((int) $propertyId)['privacy_retention_years'] ?? 0));
            if ($years < 1) {
                $this->line("Tesis {$propertyId}: saklama süresi ayarlanmamış, atlandı.");

                continue;
            }
            $ids = $anonymizer->candidates((int) $propertyId, $years);
            $this->info("Tesis {$propertyId}: {$ids->count()} profil ({$years} yıldan eski).");
            if ($this->option('dry-run')) {
                continue;
            }
            foreach ($ids as $id) {
                try {
                    $anonymizer->anonymize(Profile::query()->findOrFail($id), null, 'retention');
                    $total++;
                } catch (RuntimeException $e) {
                    $this->warn("#{$id}: ".$e->getMessage());
                }
            }
        }
        $this->info("Anonimleştirilen: {$total}");

        return self::SUCCESS;
    }
}
