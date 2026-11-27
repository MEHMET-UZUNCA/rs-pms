<?php

namespace App\Domain\Profiles\Services;

use App\Domain\Profiles\Models\Profile;
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

    /**
     * @param  array<string, mixed>  $data  profil alanları + address[...] + phone / mobile / email
     */
    public function save(int $propertyId, array $data, ?Profile $profile, ?int $userId): Profile
    {
        return DB::transaction(function () use ($propertyId, $data, $profile, $userId) {
            $address = array_filter($data['address'] ?? [], fn ($v) => $v !== null && $v !== '');
            $comms = array_intersect_key($data, array_flip(self::COMMUNICATIONS));
            unset($data['address'], $data['phone'], $data['mobile'], $data['email']);

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

            return $profile->fresh(['addresses', 'communications']);
        });
    }
}
