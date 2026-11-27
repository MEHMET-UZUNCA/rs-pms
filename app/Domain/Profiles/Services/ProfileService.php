<?php

namespace App\Domain\Profiles\Services;

use App\Domain\Profiles\Models\Profile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Profil kaydı (Fidelio Customer Relation → Profiles): bireysel misafir, şirket, acenta, kaynak, grup.
 * Görünen ad: bireyselde "Soyad, Ad", diğerlerinde firma adı. Birincil adres ve iletişim bilgileri (telefon, cep, e-posta) profille birlikte kaydedilir.
 */
class ProfileService
{
    public const TYPES = ['individual', 'company', 'travel_agent', 'source', 'group'];

    public const COMMUNICATIONS = ['phone', 'mobile', 'email'];

    /** Fidelio More Fields → Personal Documents belge tipleri. */
    public const DOCUMENT_TYPES = ['passport', 'id_card', 'kktc_id', 'tc_id', 'driving_license'];

    /** Belge numarası aramada kullanılan özet (büyük harf, boşluksuz). */
    public static function documentHash(string $number): string
    {
        return hash('sha256', mb_strtoupper(preg_replace('/\s+/', '', $number)));
    }

    /**
     * Profilin kişisel belgeleri (numara şifreli tutulur).
     *
     * @return list<array{id: int, type: string, number: string}>
     */
    public function documents(Profile $profile): array
    {
        return DB::table('pms_profile_identities')->where('profile_id', $profile->id)->orderBy('id')->get(['id', 'document_type', 'document_number'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'type' => $d->document_type, 'number' => rescue(fn () => Crypt::decryptString($d->document_number), '', false)])->all();
    }

    /**
     * @param  array<string, mixed>  $data  profil alanları + address[...] + phone / mobile / email
     */
    public function save(int $propertyId, array $data, ?Profile $profile, ?int $userId): Profile
    {
        return DB::transaction(function () use ($propertyId, $data, $profile, $userId) {
            $address = array_filter($data['address'] ?? [], fn ($v) => $v !== null && $v !== '');
            $comms = array_intersect_key($data, array_flip(self::COMMUNICATIONS));
            $documents = array_key_exists('documents', $data) ? $data['documents'] : null;
            unset($data['address'], $data['phone'], $data['mobile'], $data['email'], $data['documents']);

            $data['display_name'] = $data['type'] === 'individual'
                ? trim(($data['last_name'] ?? '').(($data['first_name'] ?? '') !== '' ? ', '.$data['first_name'] : ''), ', ')
                : (string) ($data['company_name'] ?? '');
            if (($data['marketing_consent'] ?? false) && ! $profile?->marketing_consent) {
                $data['marketing_consent_at'] = now();
                $data['marketing_consent_source'] = 'front_office';
            }

            if ($profile) {
                $profile->update($data + ['updated_by' => $userId]);
            } else {
                $profile = Profile::create($data + ['property_id' => $propertyId, 'created_by' => $userId]);
            }

            // Birincil adres
            $primary = $profile->addresses()->where('is_primary', true)->first() ?? $profile->addresses()->first();
            if ($address) {
                $primary ? $primary->update($address + ['is_primary' => true]) : $profile->addresses()->create($address + ['type' => 'home', 'is_primary' => true]);
            } elseif ($primary) {
                $primary->delete();
            }

            // Birincil iletişim bilgileri: her tür için bir kayıt
            foreach (self::COMMUNICATIONS as $type) {
                $value = trim((string) ($comms[$type] ?? ''));
                $row = $profile->communications()->where('type', $type)->orderByDesc('is_primary')->orderBy('id')->first();
                if ($value === '') {
                    $row?->delete();

                    continue;
                }
                $values = ['value' => $value, 'value_normalized' => $type === 'email' ? Str::lower($value) : preg_replace('/[^\d+]/', '', $value), 'is_primary' => true];
                $row ? $row->update($values) : $profile->communications()->create($values + ['type' => $type]);
            }

            if ($documents !== null) {
                $this->saveDocuments($profile, $documents);
            }

            return $profile->fresh(['addresses', 'communications']);
        });
    }

    /**
     * Personal Documents: satırda id varsa güncellenir, listede olmayan eski belgeler silinir, boş numaralı satır atlanır.
     *
     * @param  list<array{id?: int|string|null, type?: string|null, number?: string|null}>  $rows
     */
    private function saveDocuments(Profile $profile, array $rows): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '' || ! in_array($row['type'] ?? null, self::DOCUMENT_TYPES, true)) {
                continue;
            }
            $values = ['document_type' => $row['type'], 'document_number' => Crypt::encryptString($number), 'document_number_hash' => self::documentHash($number), 'updated_at' => now()];
            $id = (int) ($row['id'] ?? 0);
            if ($id && DB::table('pms_profile_identities')->where('id', $id)->where('profile_id', $profile->id)->exists()) {
                DB::table('pms_profile_identities')->where('id', $id)->update($values);
            } else {
                $id = DB::table('pms_profile_identities')->insertGetId($values + ['profile_id' => $profile->id, 'created_at' => now()]);
            }
            $keep[] = $id;
        }
        DB::table('pms_profile_identities')->where('profile_id', $profile->id)->whereNotIn('id', $keep ?: [0])->delete();
    }
}
