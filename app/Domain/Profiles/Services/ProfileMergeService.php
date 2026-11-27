<?php

namespace App\Domain\Profiles\Services;

use App\Domain\Profiles\Models\Profile;
use App\Support\Audit\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Profil birleştirme (Fidelio Merge, Elektraweb mükerrer profil): aynı kişinin / firmanın iki kaydı tek kayıtta toplanır.
 *  - Adaylar: aynı tipte, aynı ad-soyad (büyük/küçük harf farkı yok), aynı soyad + doğum tarihi, aynı e-posta / telefon ya da aynı kimlik belgesi.
 *  - Birleştirme: bütün bağlantılar (rezervasyonlar — misafir / şirket / acenta / kaynak / grup, bloklar, folyo ödeyeni, faturalar, kayıtlar …)
 *    kalan profile taşınır; iletişim, adres ve belgelerden kalan profilde olmayanlar eklenir; kalan profildeki boş alanlar diğerinden doldurulur.
 *    Silinen profil "merged" olur ve merged_into_id ile kalan profili gösterir (listelerde görünmez, geri izlenebilir).
 */
class ProfileMergeService
{
    /** Profil alanları: kalan profilde boşsa diğerinden alınır. */
    private const FILL = ['title', 'salutation', 'letter_greeting', 'first_name', 'middle_name', 'gender', 'birth_date', 'birth_place', 'occupation', 'position',
        'department', 'wedding_date', 'nationality_code', 'language_code', 'vip_level_id', 'tax_office', 'tax_number', 'iata_code', 'commission_pct',
        'default_rate_code_id', 'default_market_segment_id', 'default_source_code_id', 'rsone_client_id'];

    /** Özel işlenen tablolar (genel taşımaya girmez). */
    private const SPECIAL = ['pms_profiles', 'pms_profile_communications', 'pms_profile_addresses', 'pms_profile_identities'];

    /** @return Collection<int, object> aday profiller ve eşleşme nedeni */
    public function candidates(Profile $profile): Collection
    {
        $base = fn () => DB::table('pms_profiles')->where('property_id', $profile->property_id)->where('type', $profile->type)
            ->where('id', '!=', $profile->id)->whereNull('merged_into_id')->whereNull('deleted_at');
        $found = collect();
        $add = function ($rows, string $reason) use ($found) {
            foreach ($rows as $row) {
                $found[$row->id] ??= (object) ['id' => $row->id, 'display_name' => $row->display_name, 'birth_date' => $row->birth_date, 'nationality_code' => $row->nationality_code, 'reasons' => []];
                $found[$row->id]->reasons[] = $reason;
            }
        };
        $cols = ['id', 'display_name', 'birth_date', 'nationality_code'];
        $add($base()->whereRaw('LOWER(display_name) = ?', [mb_strtolower($profile->display_name)])->limit(20)->get($cols), 'name');
        if ($profile->last_name && $profile->birth_date) {
            $add($base()->whereRaw('LOWER(last_name) = ?', [mb_strtolower($profile->last_name)])->whereDate('birth_date', $profile->birth_date->toDateString())->limit(20)->get($cols), 'birth_date');
        }
        $comms = DB::table('pms_profile_communications')->where('profile_id', $profile->id)->whereNotNull('value_normalized')->where('value_normalized', '!=', '')->pluck('value_normalized');
        if ($comms->isNotEmpty()) {
            $add($base()->whereIn('id', DB::table('pms_profile_communications')->whereIn('value_normalized', $comms)->select('profile_id'))->limit(20)->get($cols), 'contact');
        }
        $docs = DB::table('pms_profile_identities')->where('profile_id', $profile->id)->pluck('document_number_hash');
        if ($docs->isNotEmpty()) {
            $add($base()->whereIn('id', DB::table('pms_profile_identities')->whereIn('document_number_hash', $docs)->select('profile_id'))->limit(20)->get($cols), 'document');
        }
        $stays = DB::table('pms_reservations')->whereIn('profile_id', $found->keys())->groupBy('profile_id')->selectRaw('profile_id, COUNT(*) AS n')->pluck('n', 'profile_id');

        return $found->values()->map(function ($c) use ($stays) {
            $c->reasons = array_values(array_unique($c->reasons));
            $c->reservations = (int) ($stays[$c->id] ?? 0);

            return $c;
        });
    }

    /** $drop profilini $keep içine birleştirir. @return array<string, int> tablo => taşınan satır */
    public function merge(Profile $keep, Profile $drop, ?int $userId): array
    {
        if ($keep->id === $drop->id || (int) $keep->property_id !== (int) $drop->property_id) {
            throw new RuntimeException(__('pms.merge.same'));
        }
        if ($keep->type !== $drop->type) {
            throw new RuntimeException(__('pms.merge.type'));
        }
        if ($keep->merged_into_id || $drop->merged_into_id) {
            throw new RuntimeException(__('pms.merge.already'));
        }

        return DB::transaction(function () use ($keep, $drop, $userId) {
            $moved = [];
            // Boş alanları doldur
            $fill = [];
            foreach (self::FILL as $field) {
                if (($keep->{$field} === null || $keep->{$field} === '') && $drop->{$field} !== null && $drop->{$field} !== '') {
                    $fill[$field] = $drop->getRawOriginal($field);
                }
            }
            if ($drop->status === 'blacklisted' && $keep->status !== 'blacklisted') {
                $fill += ['status' => 'blacklisted', 'blacklist_reason' => $drop->blacklist_reason];
            }
            foreach (['marketing_consent', 'no_mailing', 'no_emailing'] as $flag) {
                if ($drop->{$flag} && ! $keep->{$flag}) {
                    $fill[$flag] = true;
                }
            }
            if ($fill) {
                $keep->update($fill + ['updated_by' => $userId]);
            }

            // İletişim: kalan profilde aynı değer yoksa taşı
            $have = DB::table('pms_profile_communications')->where('profile_id', $keep->id)->pluck('value_normalized')->filter()->all();
            $moved['pms_profile_communications'] = DB::table('pms_profile_communications')->where('profile_id', $drop->id)
                ->where(fn ($q) => $q->whereNull('value_normalized')->orWhereNotIn('value_normalized', $have ?: ['']))
                ->update(['profile_id' => $keep->id, 'is_primary' => false]);
            // Adres: kalan profilin adresi yoksa birincil olarak, varsa ek adres olarak
            $keepHasAddress = DB::table('pms_profile_addresses')->where('profile_id', $keep->id)->exists();
            $moved['pms_profile_addresses'] = DB::table('pms_profile_addresses')->where('profile_id', $drop->id)
                ->update(['profile_id' => $keep->id] + ($keepHasAddress ? ['is_primary' => false] : []));
            // Belgeler: aynı belge yoksa taşı
            $docs = DB::table('pms_profile_identities')->where('profile_id', $keep->id)->pluck('document_number_hash')->all();
            $moved['pms_profile_identities'] = DB::table('pms_profile_identities')->where('profile_id', $drop->id)->whereNotIn('document_number_hash', $docs ?: [''])
                ->update(['profile_id' => $keep->id]);

            // Diğer bütün bağlantılar: pms_profiles'a işaret eden yabancı anahtarlar
            foreach ($this->references() as [$table, $column]) {
                try {
                    $n = DB::transaction(fn () => DB::table($table)->where($column, $drop->id)->update([$column => $keep->id]));
                } catch (UniqueConstraintViolationException) {
                    // Kalan profilde aynı kayıt zaten var (ör. istatistik, üyelik): yineleyen satırlar silinir
                    $n = DB::table($table)->where($column, $drop->id)->delete();
                }
                if ($n) {
                    $moved[$table.'.'.$column] = $n;
                }
            }
            DB::table('pms_profile_communications')->where('profile_id', $drop->id)->delete();
            DB::table('pms_profile_identities')->where('profile_id', $drop->id)->delete();

            $drop->update(['merged_into_id' => $keep->id, 'merged_at' => now(), 'status' => 'merged', 'updated_by' => $userId]);
            Audit::record('profile', $keep->id, 'merged', ['merged_profile' => $drop->id, 'name' => $drop->display_name], $moved, null, $keep->property_id);

            return array_filter($moved);
        });
    }

    /** @return list<array{0: string, 1: string}> pms_profiles'a işaret eden (tablo, kolon) çiftleri */
    private function references(): array
    {
        $refs = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (! str_starts_with($name, 'pms_') || in_array($name, self::SPECIAL, true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($name) as $fk) {
                if (($fk['foreign_table'] ?? '') === 'pms_profiles' || str_ends_with((string) ($fk['foreign_table'] ?? ''), '.pms_profiles')) {
                    foreach ($fk['columns'] as $column) {
                        $refs[] = [$name, $column];
                    }
                }
            }
        }
        // pms_profiles içindeki bağlantılar (ana iletişim kişisi); merged_into_id hariç
        $refs[] = ['pms_profiles', 'primary_contact_profile_id'];

        return $refs;
    }
}
