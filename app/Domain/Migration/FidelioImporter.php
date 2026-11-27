<?php

namespace App\Domain\Migration;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\FolioService;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Models\Property;
use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Profiles\Services\ProfileService;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Reservations\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fidelio Suite8 → RS PMS veri aktarımı (docs/06-fidelio-gecis.md, docs/fidelio/eslesme.md).
 *
 * Kaynak: PROISV view'larının dışa aktarılmış CSV dosyaları (ilk satır kolon adları; ayraç ; , sekme ya da | kendiliğinden bulunur;
 * UTF-8 değilse Windows-1254 kabul edilir). Dosya adı view adıdır: PRO_ISV_PROFILEINFO.csv, PRO_ISV_RESERVATIONINFO.csv,
 * PRO_ISV_MULTIGUEST_LIST.csv, PRO_ISV_POSTINGS.csv. İsteğe bağlı eslesme.json Fidelio kodlarını RS PMS kodlarına çevirir.
 *
 * Aktarılanlar:
 *  1. Profiller (+ birincil adres, iletişim, kimlik belgesi — şifreli), birleştirilmiş / ana kişi bağlantıları
 *  2. Kesim tarihinden sonra ayrılan rezervasyonlar (beklenen, içerideki, bekleme listesi, teklif; istenirse iptaller) + gecelik satırlar
 *  3. Ek misafirler
 *  4. İçerideki misafirlerin açık bakiyeleri: pencere başına tek "devir" kaydı (varsayılan departman kodu 879)
 *
 * Her kayıt legacy_system = 'fidelio' + legacy_id taşır; tekrar çalıştırmak çift kayıt üretmez (var olan güncellenir).
 * RS PMS'te işlem görmüş (aktarım dışı folyo kaydı olan) rezervasyonun üzerine yazılmaz.
 */
class FidelioImporter
{
    public const SYSTEM = 'fidelio';

    public const FILES = [
        'profiles' => 'PRO_ISV_PROFILEINFO',
        'reservations' => 'PRO_ISV_RESERVATIONINFO',
        'guests' => 'PRO_ISV_MULTIGUEST_LIST',
        'balances' => 'PRO_ISV_POSTINGS',
    ];

    /** Fidelio 3 harfli dil kodu → ISO 639-1. */
    private const LANGUAGES = [
        'ENG' => 'en', 'TUR' => 'tr', 'GER' => 'de', 'DEU' => 'de', 'RUS' => 'ru', 'FRE' => 'fr', 'FRA' => 'fr', 'ITA' => 'it', 'SPA' => 'es',
        'ESP' => 'es', 'HEB' => 'he', 'ARA' => 'ar', 'GRE' => 'el', 'ELL' => 'el', 'DUT' => 'nl', 'NLD' => 'nl', 'POL' => 'pl', 'UKR' => 'uk',
        'PER' => 'fa', 'FAS' => 'fa', 'AZE' => 'az', 'KAZ' => 'kk', 'SWE' => 'sv', 'NOR' => 'no', 'DAN' => 'da', 'FIN' => 'fi', 'CHI' => 'zh',
        'ZHO' => 'zh', 'JPN' => 'ja', 'KOR' => 'ko', 'POR' => 'pt', 'RUM' => 'ro', 'RON' => 'ro', 'BUL' => 'bg', 'HUN' => 'hu', 'CZE' => 'cs',
    ];

    private int $propertyId;

    private Property $property;

    private string $dir;

    /** @var array<string, mixed> */
    private array $options;

    /** @var array<string, array<string, string>> eslesme.json */
    private array $map = [];

    /** @var array<string, array<string, int>> tablo => kod => id */
    private array $lookups = [];

    /** @var array<string, int> Fidelio PROFILE_ID → pms_profiles.id */
    private array $profiles = [];

    /** @var array<string, list<int>> Fidelio RESERVATION_ID → pms_reservations.id (çok odalıda birden fazla) */
    private array $reservations = [];

    /** @var array<string, mixed> */
    private array $report = [];

    private ?int $userId = null;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly AvailabilityService $availability,
        private readonly FolioService $folios,
        private readonly PostingService $postings,
    ) {}

    /**
     * @param  array{dry_run?: bool, only?: list<string>, from?: ?string, with_cancelled?: bool, balance_code?: string, ledgers?: list<string>}  $options
     * @return array<string, mixed> doğrulama raporu
     */
    public function run(int $propertyId, string $dir, array $options = [], ?int $userId = null): array
    {
        $this->propertyId = $propertyId;
        $this->property = Property::query()->findOrFail($propertyId);
        $this->dir = rtrim($dir, '/');
        $this->userId = $userId;
        $this->options = $options + ['dry_run' => false, 'only' => array_keys(self::FILES), 'from' => null, 'with_cancelled' => false,
            'balance_code' => '879', 'ledgers' => ['0']];
        $this->options['from'] ??= $this->property->current_business_date->toDateString();
        $mapFile = $this->dir.'/eslesme.json';
        $this->map = is_readable($mapFile) ? (array) json_decode((string) file_get_contents($mapFile), true) : [];
        $this->report = ['property' => $this->property->code, 'directory' => $this->dir, 'dry_run' => (bool) $this->options['dry_run'],
            'cutoff' => $this->options['from'], 'started_at' => now()->toDateTimeString(), 'entities' => [], 'missing_codes' => [], 'totals' => []];

        $this->profiles = DB::table('pms_profiles')->where('legacy_system', self::SYSTEM)->pluck('id', 'legacy_id')->map(fn ($v) => (int) $v)->all();

        DB::beginTransaction();
        try {
            foreach (array_keys(self::FILES) as $entity) {
                if (! in_array($entity, $this->options['only'], true)) {
                    continue;
                }
                $file = $this->file(self::FILES[$entity]);
                if (! $file) {
                    $this->report['entities'][$entity] = ['file' => null, 'note' => self::FILES[$entity].' dosyası yok, atlandı'];

                    continue;
                }
                $this->{'import'.ucfirst($entity)}($file);
            }
            $this->options['dry_run'] ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->report['finished_at'] = now()->toDateTimeString();

        return $this->report;
    }

    // ---------------------------------------------------------------- profiller

    private function importProfiles(string $file): void
    {
        $batch = $this->batch('profiles', $file);
        $stats = ['read' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'by_type' => []];
        $links = [];
        foreach (self::rows($file) as $row) {
            $stats['read']++;
            $legacy = $this->s($row, 'PROFILE_ID');
            if ($legacy === null) {
                $stats['skipped']++;

                continue;
            }
            try {
                [$id, $created] = DB::transaction(fn () => $this->saveProfile($legacy, $row));
                $stats[$created ? 'created' : 'updated']++;
                $type = DB::table('pms_profiles')->where('id', $id)->value('type');
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
                foreach (['merged_into_id' => 'MERGEDTO_PROFILE_ID', 'primary_contact_profile_id' => 'PRIMARY_CONTACT_ID'] as $column => $source) {
                    if (($target = $this->s($row, $source)) !== null && $target !== $legacy) {
                        $links[] = [$id, $column, $target];
                    }
                }
            } catch (Throwable $e) {
                if (isset($this->profiles[$legacy]) && ! DB::table('pms_profiles')->where('id', $this->profiles[$legacy])->exists()) {
                    unset($this->profiles[$legacy]);   // geri alınan yeni kayıt
                }
                $stats['failed']++;
                $this->error($batch, $legacy, $e->getMessage());
            }
        }
        // İkinci geçiş: birleştirilmiş profilin hedefi, şirketin ana kişisi (hedef aynı dosyadaysa)
        $linked = 0;
        foreach ($links as [$id, $column, $target]) {
            if (isset($this->profiles[$target])) {
                DB::table('pms_profiles')->where('id', $id)->update([$column => $this->profiles[$target]]);
                $linked++;
            }
        }
        $stats['links'] = $linked;
        $this->finish($batch, $stats);
        $this->report['entities']['profiles'] = $stats;
    }

    /** @return array{0: int, 1: bool} profil id, yeni mi */
    private function saveProfile(string $legacy, array $row): array
    {
        $category = (int) $this->s($row, 'PROFILE_INTERNALCATEGORY');
        $type = match ($category) {
            1, 5, 6 => 'company', 2 => 'travel_agent', 3 => 'source', 4 => 'individual',
            default => (int) $this->s($row, 'PROFILE_TYPE') === 1 ? 'company' : 'individual',
        };
        $individual = $type === 'individual';
        $name1 = $this->s($row, 'NAME1');
        $name2 = $this->s($row, 'NAME2');
        $full = $this->s($row, 'GUESTFULLNAME');
        $display = $individual ? trim(($name1 ?? '').($name2 ? ', '.$name2 : ''), ', ') : ($name1 ?? $full ?? '');
        if ($display === '') {
            $display = $full ?? $legacy;
        }
        $sex = (int) $this->s($row, 'SEX');
        $status = match ((int) $this->s($row, 'ACTIVE_STATUS', '1')) {
            0 => 'inactive', 3 => 'terminated', 4 => 'merged', default => 'active',
        };

        $data = [
            'property_id' => $this->propertyId, 'type' => $type, 'status' => $status,
            'last_name' => $individual ? Str::limit((string) $name1, 100, '') ?: null : null,
            'first_name' => $individual ? $name2 : null,
            'middle_name' => $individual ? $this->s($row, 'NAME3') : null,
            'company_name' => $individual ? null : Str::limit($display, 150, ''),
            'display_name' => Str::limit($display, 200, ''),
            'salutation' => $this->s($row, 'ADDRESSGREETING'),
            'letter_greeting' => $this->s($row, 'LETTERGREETING'),
            'gender' => $sex === 1 ? 'male' : ($sex === 2 ? 'female' : null),
            'language_code' => $this->language($this->s($row, 'PROFILELANGUAGE')),
            'birth_date' => self::date($this->s($row, 'BIRTHDATE')),
            'wedding_date' => self::date($this->s($row, 'WEDDINGDAY')),
            'nationality_code' => $this->country($this->s($row, 'NATION')),
            'vip_level_id' => $this->lookup('vip_levels', 'pms_vip_levels', $this->s($row, 'VIPCODE'), false),
            'tax_office' => $this->s($row, 'TAXOFFICE'),
            'tax_number' => $this->s($row, 'TAXNUMBER'),
            'occupation' => $this->s($row, 'OCCUPATION'),
            'no_mailing' => self::bool($this->s($row, 'NOMAILING')),
            'no_emailing' => self::bool($this->s($row, 'NOEMAILING')),
            'store_private_data' => self::bool($this->s($row, 'STOREPRIVATEDATA'), true),
            'origin_hotel_name' => $this->s($row, 'ORIGINATED_HOTEL_NAME'),
            'origin_hotel_legacy_id' => $this->s($row, 'ORIGINATED_HOTEL_ID'),
            'merged_at' => self::dateTime($this->s($row, 'MERGETIME')),
            'legacy_system' => self::SYSTEM, 'legacy_id' => $legacy,
            'legacy_global_id' => $this->s($row, 'PROFILE_GLOBALID'),
            'legacy_created_by' => $this->s($row, 'CREATED_USER'),
            'legacy_updated_by' => $this->s($row, 'LASTUPDATEUSERNAME') ?? $this->s($row, 'LASTUPDATEUSER'),
            'updated_at' => self::dateTime($this->s($row, 'LASTUPDATETIME')) ?? now(),
        ];

        $id = $this->profiles[$legacy] ?? null;
        $created = $id === null;
        if ($created) {
            $id = (int) DB::table('pms_profiles')->insertGetId($data + ['created_at' => self::dateTime($this->s($row, 'CREATIONDATE')) ?? now(), 'created_by' => $this->userId]);
            $this->profiles[$legacy] = $id;
        } else {
            DB::table('pms_profiles')->where('id', $id)->update($data);
        }

        // Birincil adres
        $address = array_filter([
            'line1' => $this->s($row, 'STREET1'), 'line2' => $this->s($row, 'STREET2'), 'line3' => $this->s($row, 'STREET3'),
            'city' => $this->s($row, 'CITY'), 'postal_code' => $this->s($row, 'ZIPCODE'), 'state' => $this->s($row, 'STATE_SHORTDESC'),
            'country_code' => $this->country($this->s($row, 'COUNTRY')),
        ], fn ($v) => $v !== null);
        if ($address) {
            $existing = DB::table('pms_profile_addresses')->where('profile_id', $id)->where('is_primary', true)->value('id');
            $existing
                ? DB::table('pms_profile_addresses')->where('id', $existing)->update($address + ['updated_at' => now()])
                : DB::table('pms_profile_addresses')->insert($address + ['profile_id' => $id, 'type' => $individual ? 'home' : 'business', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        // İletişim: her tür için birincil kayıt
        foreach (['email' => 'EMAIL', 'phone' => 'TELEPHONE', 'mobile' => 'MOBILETELEPHONE', 'fax' => 'FAX', 'web' => 'WEBSITE'] as $kind => $column) {
            $value = $this->s($row, $column);
            if ($value === null) {
                continue;
            }
            $values = ['value' => Str::limit($value, 150, ''), 'value_normalized' => in_array($kind, ['email', 'web'], true) ? Str::lower($value) : preg_replace('/[^\d+]/', '', $value),
                'is_primary' => true, 'updated_at' => now()];
            $existing = DB::table('pms_profile_communications')->where('profile_id', $id)->where('type', $kind)->orderByDesc('is_primary')->value('id');
            $existing
                ? DB::table('pms_profile_communications')->where('id', $existing)->update($values)
                : DB::table('pms_profile_communications')->insert($values + ['profile_id' => $id, 'type' => $kind, 'created_at' => now()]);
        }

        // Kimlik belgeleri (şifreli; aynı numara ikinci kez eklenmez)
        $documents = [];
        if ($number = $this->s($row, 'PERSONAL_DOC_NUMBER')) {
            $documents[] = [$this->documentType($this->s($row, 'PERSONAL_DOC_TYPE')), $number];
        }
        if ($number = $this->s($row, 'TCIDNO')) {
            $documents[] = ['tc_id', $number];
        }
        foreach ($documents as [$docType, $number]) {
            $hash = ProfileService::documentHash($number);
            if (! DB::table('pms_profile_identities')->where('profile_id', $id)->where('document_number_hash', $hash)->exists()) {
                DB::table('pms_profile_identities')->insert(['profile_id' => $id, 'document_type' => $docType, 'document_number' => Crypt::encryptString($number),
                    'document_number_hash' => $hash, 'issuing_country' => $docType === 'tc_id' ? 'TR' : $data['nationality_code'], 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return [$id, $created];
    }

    private function documentType(?string $value): string
    {
        $v = Str::upper((string) $value);

        return match (true) {
            str_contains($v, 'PAS') => 'passport',
            str_contains($v, 'EHL') || str_contains($v, 'DRIV') => 'driving_license',
            str_contains($v, 'KKTC') => 'kktc_id',
            str_contains($v, 'TC') => 'tc_id',
            default => 'id_card',
        };
    }

    // ---------------------------------------------------------------- rezervasyonlar

    private function importReservations(string $file): void
    {
        $batch = $this->batch('reservations', $file);
        $stats = ['read_rows' => 0, 'reservations' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0,
            'by_status' => [], 'nights' => 0, 'room_amount' => 0.0, 'skipped_reasons' => []];
        $cutoff = $this->options['from'];

        // Rezervasyon × gün satırlarını grupla; kesimden önce ayrılanlar (geçmiş) baştan atlanır — onlar arşive gider.
        $groups = [];
        foreach (self::rows($file) as $row) {
            $stats['read_rows']++;
            $id = $this->s($row, 'RESERVATION_ID');
            $calc = (int) $this->s($row, 'CALCULATEDSTATUS', '0');
            $departure = self::date($this->s($row, 'DEPARTUREDATE'));
            if ($id === null || $departure === null) {
                continue;
            }
            $inHouse = in_array($calc, [2, 3, 4, 8], true);
            if (! $inHouse && $departure < $cutoff) {
                continue;
            }
            $groups[$id][] = $row;
        }

        $min = $max = null;
        foreach ($groups as $legacy => $rows) {
            $stats['reservations']++;
            usort($rows, fn ($a, $b) => strcmp((string) self::date($this->s($a, 'DETAIL_DATE')), (string) self::date($this->s($b, 'DETAIL_DATE'))));
            $head = $rows[0];
            $calc = (int) $this->s($head, 'CALCULATEDSTATUS', '0');
            $status = match ($calc) {
                0, 1, 7 => (int) $this->s($head, 'RESERVATIONSTATUS', '1') === 0 ? 'tentative' : 'confirmed',
                2, 3, 4, 8 => 'in_house',
                10 => 'waitlist',
                11 => 'inquiry',
                12 => 'cancelled',
                default => null,   // 5, 6, 9 çıkmış · 13 no-show: aktarılmaz (arşiv)
            };
            if ($status === null || ($status === 'cancelled' && ! $this->options['with_cancelled'])) {
                $stats['skipped']++;
                $reason = $status === 'cancelled' ? 'cancelled' : 'status_'.$calc;
                $stats['skipped_reasons'][$reason] = ($stats['skipped_reasons'][$reason] ?? 0) + 1;

                continue;
            }
            try {
                $result = DB::transaction(fn () => $this->saveReservation((string) $legacy, $rows, $status, $calc));
                if ($result === null) {
                    $stats['skipped']++;
                    $stats['skipped_reasons']['changed_in_rs_pms'] = ($stats['skipped_reasons']['changed_in_rs_pms'] ?? 0) + 1;

                    continue;
                }
                [$ids, $created, $nights, $amount, $from, $to] = $result;
                $stats[$created ? 'created' : 'updated'] += count($ids);
                $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + count($ids);
                $stats['nights'] += $nights;
                $stats['room_amount'] = round($stats['room_amount'] + $amount, 2);
                $min = $min === null || $from < $min ? $from : $min;
                $max = $max === null || $to > $max ? $to : $max;
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->error($batch, (string) $legacy, $e->getMessage(), ['RATECODE' => $this->s($head, 'RATECODE'), 'ROOMTYPE' => $this->s($head, 'ROOMTYPE'),
                    'MARKETCODE' => $this->s($head, 'MARKETCODE'), 'SOURCECODE' => $this->s($head, 'SOURCECODE'), 'GUARANTEECODE' => $this->s($head, 'GUARANTEECODE')]);
            }
        }

        // Onay numarası serisi aktarılan Fidelio numaralarının üstünden devam eder
        $maxNumber = DB::table('pms_reservations')->where('property_id', $this->propertyId)->where('legacy_system', self::SYSTEM)
            ->pluck('confirmation_number')->map(fn ($n) => (int) preg_replace('/\D.*$/', '', (string) $n))->max();
        if ($maxNumber) {
            $this->numbers->next($this->propertyId, 'confirmation');   // seri yoksa açılır
            DB::table('pms_number_sequences')->where('property_id', $this->propertyId)->where('type', 'confirmation')
                ->where('next_number', '<=', $maxNumber)->update(['next_number' => $maxNumber + 1]);
            $stats['confirmation_sequence_next'] = (int) DB::table('pms_number_sequences')->where('property_id', $this->propertyId)->where('type', 'confirmation')->value('next_number');
        }
        if ($min !== null) {
            $this->availability->rebuild($this->propertyId, CarbonImmutable::parse($min), CarbonImmutable::parse($max));
        }
        $this->finish($batch, $stats + ['total' => $stats['reservations']]);
        $this->report['entities']['reservations'] = $stats;
    }

    /**
     * @param  list<array<string, string>>  $rows  aynı rezervasyonun gün satırları (tarih sıralı)
     * @return array{0: list<int>, 1: bool, 2: int, 3: float, 4: string, 5: string}|null
     */
    private function saveReservation(string $legacy, array $rows, string $status, int $calc): ?array
    {
        $head = $rows[0];
        $arrival = self::date($this->s($head, 'ARRIVALDATE')) ?? self::date($this->s($head, 'DETAIL_DATE'));
        $departure = self::date($this->s($head, 'DEPARTUREDATE'));
        $dayUse = in_array($calc, [7, 8], true) || $arrival === $departure;
        $profileLegacy = $this->s($head, 'PROFILE_ID');
        $profileId = $profileLegacy !== null ? ($this->profiles[$profileLegacy] ?? null) : null;
        if (! $profileId) {
            throw new \RuntimeException("Profil aktarılmamış (PROFILE_ID {$profileLegacy})");
        }
        $roomTypeId = $this->lookup('room_types', 'pms_room_types', $this->s($head, 'ROOMTYPE'));
        $chargeRoomTypeId = $this->lookup('room_types', 'pms_room_types', $this->s($head, 'RATEROOMTYPE'), false) ?? $roomTypeId;
        $rateCodeId = $this->lookup('rate_codes', 'pms_rate_codes', $this->s($head, 'RATECODE'));
        $marketId = $this->lookup('market_segments', 'pms_market_segments', $this->s($head, 'MARKETCODE'));
        $sourceId = $this->lookup('source_codes', 'pms_source_codes', $this->s($head, 'SOURCECODE'));
        $typeId = $this->lookup('reservation_types', 'pms_reservation_types', $this->s($head, 'GUARANTEECODE'), false)
            ?? $this->defaultReservationType($status === 'tentative');
        $base = $this->property->base_currency;
        $currency = Str::upper((string) ($this->s($head, 'CURRENCY') ?? $base));
        $rooms = max(1, (int) $this->s($head, 'NOOFROOMS', '1'));
        $current = $this->property->current_business_date->toDateString();

        // Gecelik satırlar (Fidelio DETAIL_DATE); günübirlikte tek satır
        $nightRows = [];
        foreach ($rows as $r) {
            $date = self::date($this->s($r, 'DETAIL_DATE')) ?? $arrival;
            if (! $dayUse && ($date < $arrival || $date >= $departure)) {
                continue;
            }
            $foreign = $currency !== $base;
            $manual = self::number($this->s($r, $foreign ? 'FOREIGNRATEMANUAL' : 'RATEMANUAL'));
            $amount = $manual ?? self::number($this->s($r, $foreign ? 'FOREIGNRATECODEAMOUNT' : 'RATECODEAMOUNT')) ?? 0.0;
            $ch = (int) $this->s($r, 'CH', '0');
            $nightRows[$date] = [
                'stay_date' => $date,
                'room_type_id' => $this->lookup('room_types', 'pms_room_types', $this->s($r, 'ROOMTYPE'), false) ?? $roomTypeId,
                'charge_room_type_id' => $this->lookup('room_types', 'pms_room_types', $this->s($r, 'RATEROOMTYPE'), false) ?? $chargeRoomTypeId,
                'room_id' => $this->lookup('rooms', 'pms_rooms', $this->s($r, 'ROOMNUMBER'), false, 'number'),
                'rate_code_id' => $this->lookup('rate_codes', 'pms_rate_codes', $this->s($r, 'RATECODE'), false) ?? $rateCodeId,
                'market_segment_id' => $this->lookup('market_segments', 'pms_market_segments', $this->s($r, 'MARKETCODE'), false) ?? $marketId,
                'adults' => (int) $this->s($r, 'NOOFADULTS', '1'),
                'children' => (int) $this->s($r, 'CHILDREN', '0'), 'free_children' => (int) $this->s($r, 'FREECHILD', '0'),
                'child_band_1' => (int) $this->s($r, 'CA1', '0'), 'child_band_2' => (int) $this->s($r, 'CA2', '0'),
                'child_band_3' => (int) $this->s($r, 'CA3', '0'), 'child_other' => (int) $this->s($r, 'OTHERCA', '0'),
                'amount' => round($amount, 2),
                'net_amount' => round(self::number($this->s($r, $foreign ? 'FOREIGNNETRATECODEAMOUNT' : 'NETRATECODEAMOUNT')) ?? $amount, 2),
                'package_amount' => self::number($this->s($r, $foreign ? 'PACKAGEFOREIGNAMOUNT' : 'PACKAGEAMOUNT')) ?? 0,
                'package_included_amount' => self::number($this->s($r, $foreign ? 'PACKAGEFOREIGNAMOUNT_INCLUDED' : 'PACKAGEAMOUNT_INCLUDED')) ?? 0,
                'manual_amount' => $manual, 'is_overridden' => $manual !== null,
                'currency_code' => $currency,
                'exchange_rate' => self::number($this->s($r, 'EXCHANGERATE')),
                'foreign_amount' => $foreign ? round($amount, 2) : null,
                'is_complimentary' => $ch === 1, 'is_house_use' => $ch === 2,
                'status' => $status === 'cancelled' ? 'cancelled' : 'expected',
                'legacy_id' => $this->s($r, 'DETAIL_ID'),
            ];
        }
        if (! $nightRows) {
            throw new \RuntimeException('Gece satırı yok');
        }
        $nightRows = array_values($nightRows);
        $first = $nightRows[0];
        $currentNight = collect($nightRows)->firstWhere('stay_date', $current) ?? $first;

        $header = [
            'property_id' => $this->propertyId, 'status' => $status, 'reservation_type_id' => $typeId, 'profile_id' => $profileId,
            'company_profile_id' => $this->linkedProfile($this->s($head, 'COMPANYNAME_ID')),
            'agent_profile_id' => $this->linkedProfile($this->s($head, 'AGENTNAME_ID')),
            'source_profile_id' => $this->namedProfile('source', $this->s($head, 'SOURCENAME')),
            'share_group_id' => ctype_digit((string) $this->s($head, 'SHARENUMBER')) ? (int) $this->s($head, 'SHARENUMBER') : null,
            'booker_name' => $this->s($head, 'BOOKER'),
            'arrival_date' => $arrival, 'departure_date' => $departure,
            'original_departure_date' => self::date($this->s($head, 'ORIGINAL_DEPARTUREDATE')) ?? $departure,
            'nights' => $dayUse ? 0 : count($nightRows),
            'sale_date' => self::date($this->s($head, 'SALEDATE')),
            'eta' => self::time($this->s($head, 'EXPECTED_ARRIVALTIME') ?? $this->s($head, 'ARRIVALTIME')),
            'etd' => self::time($this->s($head, 'EXPECTED_DEPARTURETIME') ?? $this->s($head, 'DEPARTURETIME')),
            'checked_in_at' => $status === 'in_house' ? (self::dateTime($this->s($head, 'ACTUAL_ARRIVALTIME')) ?? $arrival.' 14:00:00') : null,
            'check_in_business_date' => $status === 'in_house' ? $arrival : null,
            'room_type_id' => $currentNight['room_type_id'], 'charge_room_type_id' => $currentNight['charge_room_type_id'],
            'room_id' => $currentNight['room_id'],
            'adults' => $first['adults'], 'children' => $first['children'], 'free_children' => $first['free_children'],
            'child_band_1' => $first['child_band_1'], 'child_band_2' => $first['child_band_2'], 'child_band_3' => $first['child_band_3'], 'child_other' => $first['child_other'],
            'rate_code_id' => $first['rate_code_id'], 'currency_code' => $currency,
            'is_rate_overridden' => collect($nightRows)->contains('is_overridden', true),
            'total_room_amount' => round(collect($nightRows)->sum('amount'), 2), 'total_package_amount' => round(collect($nightRows)->sum('package_amount'), 2),
            'is_complimentary' => $first['is_complimentary'], 'is_house_use' => $first['is_house_use'],
            'market_segment_id' => $first['market_segment_id'], 'source_code_id' => $sourceId,
            'channel_code_id' => $this->lookup('channel_codes', 'pms_channel_codes', $this->s($head, 'CHANNELCODE'), false),
            'vip_level_id' => $this->lookup('vip_levels', 'pms_vip_levels', $this->s($head, 'VIPCODE'), false),
            'credit_limit' => self::number($this->s($head, 'CREDITLIMIT')),
            'payment_instructions' => Str::limit((string) $this->s($head, 'BILLINGINFO'), 500, '') ?: null,
            'pos_discount_code' => $this->s($head, 'POSDISCOUNT'),
            'external_reference' => $this->s($head, 'CRSRESNR'),
            'guest_comments' => $this->s($head, 'REMARKS'),
            'is_day_use' => $dayUse,
            'cancelled_at' => $status === 'cancelled' ? (self::dateTime($this->s($head, 'LASTUPDATETIME')) ?? now()) : null,
            'legacy_system' => self::SYSTEM,
            'legacy_created_by' => $this->s($head, 'CREATED_USERNAME') ?? $this->s($head, 'CREATED_USER'),
            'legacy_updated_by' => $this->s($head, 'LASTUPDATE_USERNAME') ?? $this->s($head, 'LASTUPDATE_USER'),
            'updated_at' => now(),
        ];

        // Fidelio'da çok odalı satır: oda sayısı kadar rezervasyon, ortak booking
        $keys = [$legacy];
        for ($i = 2; $i <= $rooms; $i++) {
            $keys[] = $legacy.'-'.$i;
        }
        $existing = DB::table('pms_reservations')->where('property_id', $this->propertyId)->where('legacy_system', self::SYSTEM)
            ->whereIn('legacy_id', $keys)->pluck('id', 'legacy_id')->all();
        if ($existing && DB::table('pms_postings')->whereIn('reservation_id', array_values($existing))->where('source', '<>', 'import')->exists()) {
            return null;   // RS PMS'te işlem görmüş: üzerine yazılmaz
        }
        $bookingId = null;
        if ($rooms > 1) {
            $bookingId = DB::table('pms_reservations')->whereIn('id', array_values($existing) ?: [0])->value('booking_id')
                ?? DB::table('pms_bookings')->insertGetId(['property_id' => $this->propertyId, 'booking_number' => $this->numbers->next($this->propertyId, 'booking'),
                    'name' => $this->s($head, 'PARTY_LONGDESC') ?? DB::table('pms_profiles')->where('id', $profileId)->value('display_name'),
                    'booker_name' => $this->s($head, 'BOOKER'), 'legacy_id' => $legacy, 'created_at' => now(), 'updated_at' => now()]);
        }

        $ids = [];
        foreach ($keys as $i => $key) {
            $values = $header + ['booking_id' => $bookingId, 'legacy_id' => $key];
            if ($i > 0) {
                $values['room_id'] = null;   // ek odalara oda RS PMS'te atanır
            }
            if (isset($existing[$key])) {
                $id = (int) $existing[$key];
                DB::table('pms_reservations')->where('id', $id)->update($values);
                DB::table('pms_reservation_nights')->where('reservation_id', $id)->delete();
            } else {
                $id = (int) DB::table('pms_reservations')->insertGetId($values + [
                    'confirmation_number' => $i === 0 ? $legacy : $key,
                    'created_at' => self::dateTime($this->s($head, 'CREATED_DATE')) ?? now(), 'created_by' => $this->userId,
                ]);
                DB::table('pms_reservation_status_logs')->insert(['reservation_id' => $id, 'from_status' => null, 'to_status' => $status,
                    'business_date' => $this->property->current_business_date->toDateString(), 'user_id' => $this->userId, 'created_at' => now()]);
            }
            foreach ($nightRows as $night) {
                DB::table('pms_reservation_nights')->insert(array_merge($night, ['reservation_id' => $id, 'property_id' => $this->propertyId,
                    'room_id' => $i === 0 ? $night['room_id'] : null, 'legacy_id' => $i === 0 ? $night['legacy_id'] : null, 'created_at' => now(), 'updated_at' => now()]));
            }
            if ($status === 'in_house') {
                if ($values['room_id']) {
                    DB::table('pms_rooms')->where('id', $values['room_id'])->update(['fo_status' => 'occupied', 'current_reservation_id' => $id]);
                }
                $this->folios->window(Reservation::query()->findOrFail($id), 1);
            }
            $ids[] = $id;
        }
        $this->reservations[$legacy] = $ids;
        $to = $dayUse ? $arrival : CarbonImmutable::parse($departure)->subDay()->toDateString();

        return [$ids, ! $existing, count($nightRows) * count($ids), (float) $header['total_room_amount'] * count($ids), $arrival, max($arrival, $to)];
    }

    private function defaultReservationType(bool $tentative): int
    {
        $query = DB::table('pms_reservation_types')->where('property_id', $this->propertyId)->orderBy('id');

        return (int) ((clone $query)->where('is_guaranteed', ! $tentative)->value('id') ?? $query->value('id'));
    }

    private function linkedProfile(?string $legacy): ?int
    {
        return $legacy !== null ? ($this->profiles[$legacy] ?? null) : null;
    }

    private function namedProfile(string $type, ?string $name): ?int
    {
        if ($name === null) {
            return null;
        }
        $key = 'profile:'.$type;
        $this->lookups[$key] ??= DB::table('pms_profiles')->where('type', $type)->whereNull('deleted_at')->pluck('id', 'display_name')
            ->mapWithKeys(fn ($id, $n) => [Str::upper((string) $n) => (int) $id])->all();

        return $this->lookups[$key][Str::upper($name)] ?? null;
    }

    // ---------------------------------------------------------------- ek misafirler

    private function importGuests(string $file): void
    {
        $batch = $this->batch('guests', $file);
        $stats = ['read' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];
        $this->loadReservationMap();
        foreach (self::rows($file) as $row) {
            $stats['read']++;
            $resIds = $this->reservations[(string) $this->s($row, 'RESERVATION_ID')] ?? [];
            $profileId = $this->profiles[(string) $this->s($row, 'MULTIGUEST_PROFILE_ID')] ?? null;
            if (! $resIds || ! $profileId) {
                $stats['skipped']++;

                continue;
            }
            $resId = $resIds[0];
            if (DB::table('pms_reservations')->where('id', $resId)->value('profile_id') == $profileId
                || DB::table('pms_reservation_guests')->where('reservation_id', $resId)->where('profile_id', $profileId)->exists()) {
                $stats['skipped']++;

                continue;
            }
            DB::table('pms_reservation_guests')->insert(['reservation_id' => $resId, 'profile_id' => $profileId, 'role' => 'accompanying', 'created_at' => now(), 'updated_at' => now()]);
            $stats['created']++;
        }
        $this->finish($batch, $stats);
        $this->report['entities']['guests'] = $stats;
    }

    // ---------------------------------------------------------------- açık bakiyeler

    private function importBalances(string $file): void
    {
        $batch = $this->batch('balances', $file);
        $stats = ['read' => 0, 'used' => 0, 'windows' => 0, 'posted' => 0, 'skipped_ledger' => 0, 'not_in_house' => 0, 'failed' => 0,
            'fidelio_balance' => 0.0, 'imported_balance' => 0.0];
        $this->loadReservationMap();
        $code = TransactionCode::query()->where('property_id', $this->propertyId)->where('code', $this->options['balance_code'])->first();
        if (! $code) {
            throw new \RuntimeException("Devir departman kodu bulunamadı: {$this->options['balance_code']}");
        }
        $inHouse = DB::table('pms_reservations')->where('property_id', $this->propertyId)->where('legacy_system', self::SYSTEM)
            ->where('status', 'in_house')->pluck('id', 'legacy_id')->all();

        // rezervasyon → pencere → bakiye (harcama +, ödeme −)
        $balances = [];
        foreach (self::rows($file) as $row) {
            $stats['read']++;
            $res = (string) $this->s($row, 'POSTING_RES_ID');
            if (! isset($inHouse[$res])) {
                $stats['not_in_house']++;

                continue;
            }
            if (! in_array((string) $this->s($row, 'POSTING_LEDGERSTATUS', '0'), $this->options['ledgers'], true)) {
                $stats['skipped_ledger']++;

                continue;
            }
            $amount = self::number($this->s($row, 'POSTING_SUMUPAMOUNT')) ?? 0.0;
            $signed = (int) $this->s($row, 'CDT', '1') === 5 ? -$amount : $amount;
            $window = (string) ($this->s($row, 'POSTING_WINDOW_ID') ?? '0');
            $posting = (int) $this->s($row, 'POSTING_ID', '0');
            $balances[$res][$window] ??= ['amount' => 0.0, 'first' => $posting];
            $balances[$res][$window]['amount'] += $signed;
            $balances[$res][$window]['first'] = min($balances[$res][$window]['first'], $posting);
            $stats['used']++;
        }

        foreach ($balances as $res => $windows) {
            uasort($windows, fn ($a, $b) => $a['first'] <=> $b['first']);
            $number = 0;
            foreach ($windows as $windowId => $w) {
                $number = min(8, $number + 1);
                $amount = round($w['amount'], 2);
                $stats['windows']++;
                $stats['fidelio_balance'] = round($stats['fidelio_balance'] + $amount, 2);
                if (abs($amount) < 0.005) {
                    continue;
                }
                try {
                    $reservation = Reservation::query()->findOrFail((int) $inHouse[$res]);
                    $folio = $this->folios->window($reservation, $number);
                    Folio::query()->whereKey($folio->id)->update(['legacy_id' => (string) $windowId]);
                    $this->postings->charge($folio, $code, $amount, 1, 'Fidelio devir bakiyesi', [
                        'source' => 'import', 'legacy_id' => 'BAL-'.$res.'-'.$windowId, 'idempotency_key' => 'fidelio-balance-'.$res.'-'.$windowId,
                        'posted_by' => $this->userId, 'note' => 'Fidelio pencere '.$windowId,
                    ]);
                    $stats['posted']++;
                    $stats['imported_balance'] = round($stats['imported_balance'] + $amount, 2);
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->error($batch, $res.'/'.$windowId, $e->getMessage());
                }
            }
        }
        $this->finish($batch, $stats + ['total' => $stats['windows'], 'imported' => $stats['posted']]);
        $this->report['entities']['balances'] = $stats;
    }

    private function loadReservationMap(): void
    {
        if ($this->reservations) {
            return;
        }
        foreach (DB::table('pms_reservations')->where('property_id', $this->propertyId)->where('legacy_system', self::SYSTEM)->get(['id', 'legacy_id']) as $r) {
            $this->reservations[preg_replace('/-\d+$/', '', (string) $r->legacy_id)][] = (int) $r->id;
        }
    }

    // ---------------------------------------------------------------- yardımcılar

    /** Kod → id; eslesme.json çevirisi uygulanır. Bulunamayan zorunlu kod hata, isteğe bağlı kod rapora "eksik kod" olarak yazılır. */
    private function lookup(string $kind, string $table, ?string $code, bool $required = true, string $column = 'code'): ?int
    {
        if ($code === null) {
            if ($required) {
                throw new \RuntimeException("{$kind}: kod boş");
            }

            return null;
        }
        $mapped = (string) ($this->map[$kind][$code] ?? $code);
        if (! isset($this->lookups[$table])) {
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'property_id')) {
                $query->where('property_id', $this->propertyId);
            }
            $this->lookups[$table] = $query->pluck('id', $column)->mapWithKeys(fn ($id, $c) => [Str::upper((string) $c) => (int) $id])->all();
        }
        $id = $this->lookups[$table][Str::upper($mapped)] ?? null;
        if ($id === null) {
            $this->report['missing_codes'][$kind][$code] = ($this->report['missing_codes'][$kind][$code] ?? 0) + 1;
            if ($required) {
                throw new \RuntimeException("{$kind}: '{$code}' RS PMS'te yok");
            }
        }

        return $id;
    }

    private function country(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $this->lookups['countries'] ??= DB::table('pms_countries')->get(['code', 'iso3', 'fidelio_code'])
            ->flatMap(fn ($c) => array_filter([$c->code => $c->code, (string) $c->iso3 => $c->code, (string) $c->fidelio_code => $c->code], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH))->all();
        $mapped = (string) ($this->map['countries'][$code] ?? $code);
        $found = $this->lookups['countries'][Str::upper($mapped)] ?? null;
        if ($found === null) {
            $this->report['missing_codes']['countries'][$code] = ($this->report['missing_codes']['countries'][$code] ?? 0) + 1;
        }

        return $found;
    }

    private function language(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $code = Str::upper($code);

        return strlen($code) === 2 ? Str::lower($code) : (self::LANGUAGES[$code] ?? null);
    }

    /** Satırdan değer (boş → $default). */
    private function s(array $row, string $column, ?string $default = null): ?string
    {
        $v = isset($row[$column]) ? trim((string) $row[$column]) : '';

        return $v === '' || Str::upper($v) === 'NULL' ? $default : $v;
    }

    private function file(string $view): ?string
    {
        foreach (glob($this->dir.'/*') ?: [] as $path) {
            if (preg_match('/^'.preg_quote($view, '/').'\.(csv|txt|tsv)$/i', basename($path))) {
                return $path;
            }
        }

        return null;
    }

    private function batch(string $entity, string $file): int
    {
        return (int) DB::table('pms_legacy_import_batches')->insertGetId(['entity' => $entity, 'source' => self::SYSTEM,
            'parameters' => json_encode(['file' => basename($file)] + array_intersect_key($this->options, array_flip(['from', 'with_cancelled', 'balance_code', 'ledgers', 'dry_run']))),
            'started_at' => now(), 'run_by' => $this->userId]);
    }

    /** @param array<string, mixed> $stats */
    private function finish(int $batch, array $stats): void
    {
        DB::table('pms_legacy_import_batches')->where('id', $batch)->update([
            'finished_at' => now(), 'total' => (int) ($stats['total'] ?? $stats['read'] ?? 0),
            'imported' => (int) ($stats['imported'] ?? (($stats['created'] ?? 0) + ($stats['updated'] ?? 0))),
            'skipped' => (int) ($stats['skipped'] ?? 0), 'failed' => (int) ($stats['failed'] ?? 0),
        ]);
    }

    /** Hata kaydı. Kişisel veri yazılmaz; yalnız kodlar. */
    private function error(int $batch, string $legacy, string $message, ?array $payload = null): void
    {
        DB::table('pms_legacy_import_errors')->insert(['batch_id' => $batch, 'legacy_id' => Str::limit($legacy, 50, ''), 'error' => Str::limit($message, 500, ''),
            'payload' => $payload ? json_encode($payload) : null, 'created_at' => now()]);
        $this->report['errors'][] = $legacy.': '.$message;
    }

    /**
     * CSV satırları (başlık → değer). Ayraç ilk satırdan bulunur; UTF-8 değilse Windows-1254'ten çevrilir.
     *
     * @return \Generator<int, array<string, string>>
     */
    public static function rows(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        $sample = (string) fread($handle, 65536);
        rewind($handle);
        $convert = ! mb_check_encoding(substr($sample, 0, strrpos($sample, "\n") ?: strlen($sample)), 'UTF-8');
        $firstLine = strtok($sample, "\n") ?: '';
        $delimiter = collect([';', ',', "\t", '|'])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();
        $header = null;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null]) {
                continue;
            }
            if ($convert) {
                $cells = array_map(fn ($c) => mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1254'), $cells);
            }
            if ($header === null) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $header = array_map(fn ($h) => Str::upper(trim((string) $h, " \"\r")), $cells);

                continue;
            }
            $cells = array_pad(array_slice($cells, 0, count($header)), count($header), '');
            yield array_combine($header, array_map(fn ($c) => rtrim((string) $c, "\r"), $cells));
        }
        fclose($handle);
    }

    public static function date(?string $value): ?string
    {
        $dt = self::parseDate($value);

        return $dt?->toDateString();
    }

    public static function dateTime(?string $value): ?string
    {
        return self::parseDate($value)?->toDateTimeString();
    }

    public static function time(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/(?:^|\D)(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?\s*$/', trim($value), $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    private static function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d', 'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
            'd-M-y H.i.s', 'd-M-y', 'd-M-Y H:i:s', 'd-M-Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!'.$format, preg_replace('/\.\d+$/', '', $value));
            if ($dt !== false && \DateTimeImmutable::getLastErrors() === false) {
                return CarbonImmutable::instance($dt);
            }
        }
        $ts = strtotime($value);

        return $ts !== false ? CarbonImmutable::createFromTimestamp($ts) : null;
    }

    /** "1.234,56" / "1,234.56" / "1234,56" / "1234.56" → float. */
    public static function number(?string $value): ?float
    {
        $v = str_replace([' ', "\u{00A0}"], '', trim((string) $value));
        if ($v === '' || ! preg_match('/\d/', $v)) {
            return null;
        }
        $comma = strrpos($v, ',');
        $dot = strrpos($v, '.');
        if ($comma !== false && $dot !== false) {
            $v = $comma > $dot ? str_replace(',', '.', str_replace('.', '', $v)) : str_replace(',', '', $v);
        } elseif ($comma !== false) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private static function bool(?string $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return in_array(Str::upper($value), ['1', 'Y', 'YES', 'T', 'TRUE', 'E', 'EVET'], true);
    }
}
