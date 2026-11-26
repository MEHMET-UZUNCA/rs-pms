<?php

namespace App\Domain\NightAudit\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fidelio "Answers for Automatic Night Audit": her kapanış kontrolünde ne yapılacağı.
 *  stop     — kontrolde kayıt varsa kapanış durur (amir onayıyla geçilebilir).
 *  continue — kapanış devam eder; kayıtlara kontrolün otomatik işlemi uygulanır.
 * Rocks Hotel'de hepsi Stop (Fidelio ekranı). Ayar pms_settings'te "night_audit.answers" anahtarında tutulur.
 */
class NightAuditAnswers
{
    public const KEY = 'night_audit.answers';

    /** Cevabı değiştirilebilen kontroller ve "continue" seçilince yapılan işlem. */
    public const CONFIGURABLE = [
        'arrivals_pending' => 'no_show',          // gelmeyen misafir → No Show
        'no_show_balance' => 'no_show',           // bakiyesi olan gelmeyen misafir → No Show, folyo açık kalır
        'due_out_accounts' => 'extend',           // süresi dolan hesap (FA) → bir gün uzatılır
        'expected_accounts' => 'activate',        // açılması beklenen hesap (FA) → açılır
    ];

    /** Her zaman Stop: çıkışı yapılmamış misafir ve açık kasa kapanışta kendiliğinden çözülemez. */
    public const FIXED = ['departures_pending', 'open_cashier_shifts'];

    public const ANSWERS = ['stop', 'continue'];

    /** @return array<string, string> kontrol kodu => stop / continue */
    public static function get(int $propertyId): array
    {
        $stored = DB::table('pms_settings')->where('property_id', $propertyId)->where('key', self::KEY)->value('value');
        $stored = is_string($stored) ? (array) json_decode($stored, true) : [];

        $answers = array_fill_keys(self::FIXED, 'stop');
        foreach (array_keys(self::CONFIGURABLE) as $code) {
            $answers[$code] = in_array($stored[$code] ?? null, self::ANSWERS, true) ? $stored[$code] : 'stop';
        }

        return $answers;
    }

    /** @param array<string, string> $answers */
    public static function save(int $propertyId, array $answers, ?int $userId): void
    {
        $value = [];
        foreach (array_keys(self::CONFIGURABLE) as $code) {
            $value[$code] = in_array($answers[$code] ?? null, self::ANSWERS, true) ? $answers[$code] : 'stop';
        }
        DB::table('pms_settings')->updateOrInsert(['property_id' => $propertyId, 'key' => self::KEY],
            ['value' => json_encode($value), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
    }

    public static function severity(string $answer): string
    {
        return $answer === 'continue' ? 'warn' : 'block';
    }
}
