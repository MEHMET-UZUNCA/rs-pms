<?php

namespace App\Domain\Profiles\Services;

use App\Domain\Profiles\Models\Profile;
use App\Support\Audit\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kişisel verilerin korunması (KVKK / KKTC 89/2007): bireysel misafir profilinin kişisel bilgileri silinir, kayıt "Anonim" olarak kalır.
 * Rezervasyon, folyo, fatura ve istatistik kayıtları (yasal saklama) korunur; yalnız kişiyi tanıtan bilgiler gider:
 * ad, soyad, unvan, hitap, doğum tarihi / yeri, meslek, pozisyon, iletişim, adres, kimlik belgeleri, garanti kartı, notlar.
 * Gelecek ya da içeride rezervasyonu olan profil anonimleştirilemez.
 */
class ProfileAnonymizer
{
    public const CLEAR = ['title', 'salutation', 'letter_greeting', 'first_name', 'middle_name', 'gender', 'birth_date', 'birth_place', 'father_name', 'mother_name',
        'marital_status', 'occupation', 'position', 'department', 'wedding_date', 'tax_office', 'tax_number', 'photo_path', 'blacklist_reason'];

    public function anonymize(Profile $profile, ?int $userId, string $reason = 'request'): void
    {
        if ($profile->type !== 'individual') {
            throw new RuntimeException(__('pms.kvkk.only_individual'));
        }
        $active = DB::table('pms_reservations')->where('profile_id', $profile->id)->whereIn('status', ['confirmed', 'tentative', 'waitlist', 'inquiry', 'in_house'])->exists();
        if ($active) {
            throw new RuntimeException(__('pms.kvkk.has_active'));
        }
        DB::transaction(function () use ($profile, $userId, $reason) {
            $profile->update(array_fill_keys(self::CLEAR, null) + ['last_name' => 'Anonim', 'display_name' => 'Anonim #'.$profile->id,
                'store_private_data' => false, 'marketing_consent' => false, 'no_mailing' => true, 'no_emailing' => true, 'updated_by' => $userId]);
            DB::table('pms_profile_communications')->where('profile_id', $profile->id)->delete();
            DB::table('pms_profile_addresses')->where('profile_id', $profile->id)->delete();
            DB::table('pms_profile_identities')->where('profile_id', $profile->id)->delete();
            DB::table('pms_payment_tokens')->where('profile_id', $profile->id)->delete();
            DB::table('pms_reservations')->where('profile_id', $profile->id)->update(['booker_name' => null]);
            DB::table('pms_audit_logs')->where('auditable_type', 'profile')->where('auditable_id', $profile->id)->update(['old_values' => null, 'new_values' => null]);
            Audit::record('profile', $profile->id, 'anonymized', null, ['reason' => $reason], null, $profile->property_id);
        });
    }

    /**
     * Toplu anonimleştirme adayları: son konaklaması (ya da kaydı) $years yıldan eski, etkin rezervasyonu olmayan bireysel profiller.
     *
     * @return Collection<int, int>
     */
    public function candidates(int $propertyId, int $years): Collection
    {
        $limit = now()->subYears($years)->toDateString();

        return DB::table('pms_profiles as p')->where('p.property_id', $propertyId)->where('p.type', 'individual')->whereNull('p.merged_into_id')
            ->where('p.last_name', '!=', 'Anonim')->where('p.created_at', '<', $limit)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pms_reservations as r')->whereColumn('r.profile_id', 'p.id')
                ->where(fn ($w) => $w->where('r.departure_date', '>=', $limit)->orWhereIn('r.status', ['confirmed', 'tentative', 'waitlist', 'inquiry', 'in_house'])))
            ->orderBy('p.id')->pluck('p.id');
    }
}
