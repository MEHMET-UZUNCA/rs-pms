<?php

namespace Database\Seeders;

use App\Domain\Rates\Services\FidelioDepartmentCodeImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rocks Hotel tanım verileri — Fidelio'nun departman kodlarının tamamı, outlet'ler, vergiler, yetkiler (docs/fidelio/eslesme.md §15).
 * Kod numaraları ve adları Fidelio ile aynıdır. Vergi oranları, Simphony RVC numaraları ve Logo hesap kodları
 * otelden alınınca ayrıca girilecek (burada uydurulmaz). Birden fazla çalıştırılabilir; var olan kaydı günceller.
 */
class RocksHotelReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['TRY', 'TL', 'Türk Lirası', '₺', 1], ['EUR', 'EUR', 'Euro', '€', 2], ['USD', 'USD', 'ABD Doları', '$', 3], ['GBP', 'GBP', 'İngiliz Sterlini', '£', 4],
            ['CHF', 'CHF', 'İsviçre Frangı', 'Fr', 5], ['SEK', 'SEK', 'İsveç Kronu', 'kr', 6], ['CAD', 'CAD', 'Kanada Doları', '$', 7], ['NOK', 'NOK', 'Norveç Kronu', 'kr', 8],
            ['JPY', 'JPY', 'Japon Yeni', '¥', 9], ['SAR', 'SAR', 'Suudi Arabistan Riyali', 'SR', 10], ['KWD', 'KWD', 'Kuveyt Dinarı', 'KD', 11],
            ['AUD', 'AUD', 'Avustralya Doları', '$', 12], ['DKK', 'DKK', 'Danimarka Kronu', 'kr', 13]] as [$code, $short, $name, $symbol, $sort]) {
            DB::table('pms_currencies')->updateOrInsert(['code' => $code], ['short_code' => $short, 'name' => $name, 'symbol' => $symbol, 'decimals' => 2, 'is_active' => true, 'sort' => $sort, 'updated_at' => now()]);
        }
        foreach ([['CY', 'CYP', 'Kıbrıs', 'Cyprus', 'Kıbrıslı', '+90'], ['TR', 'TUR', 'Türkiye', 'Turkey', 'Türk', '+90'], ['GB', 'GBR', 'Birleşik Krallık', 'United Kingdom', 'İngiliz', '+44'],
            ['DE', 'DEU', 'Almanya', 'Germany', 'Alman', '+49'], ['RU', 'RUS', 'Rusya', 'Russia', 'Rus', '+7'], ['IL', 'ISR', 'İsrail', 'Israel', 'İsrailli', '+972']] as [$code, $iso3, $tr, $en, $nat, $phone]) {
            DB::table('pms_countries')->updateOrInsert(['code' => $code], ['iso3' => $iso3, 'name_tr' => $tr, 'name_en' => $en, 'nationality_tr' => $nat, 'phone_prefix' => $phone, 'updated_at' => now()]);
        }

        DB::table('pms_properties')->updateOrInsert(['code' => 'RCK'], [
            'name' => 'Rocks Hotel', 'country_code' => 'CY', 'base_currency' => 'TRY', 'timezone' => 'Asia/Nicosia',
            'current_business_date' => DB::table('pms_properties')->where('code', 'RCK')->value('current_business_date') ?? now()->toDateString(),
            'is_active' => true, 'updated_at' => now(),
        ]);
        $propertyId = (int) DB::table('pms_properties')->where('code', 'RCK')->value('id');

        // Fidelio departman kodu grupları. Adlar geçicidir (kodların içeriğinden verildi); Fidelio "Department Code Groups"
        // ekranındaki adlarla ekrandan değiştirilebilir. [kod => [ad, tip]]
        $groups = [
            '10' => ['Room Revenue', 'revenue'], '11' => ['Room Revenue', 'revenue'],   // Fidelio'daki ad (100 ve 107 kod pencerelerinden) '12' => ['Dragon Rest', 'revenue'],
            '15' => ['Pansiyon', 'revenue'], '16' => ['Bireysel pansiyon', 'revenue'], '20' => ['Yiyecek', 'revenue'],
            '30' => ['İçecek', 'revenue'], '40' => ['Telefon ve internet', 'revenue'], '50' => ['Diğer gelirler', 'revenue'],
            '55' => ['KDV indirimi', 'revenue'], '60' => ['Çamaşırhane', 'revenue'], '75' => ['Paket farkları', 'revenue'],
            '80' => ['Paid-out', 'non_revenue'], '90' => ['Ödemeler', 'payment'], '95' => ['Döviz ödemeleri', 'payment'],
            'GEN' => ['Sistem kodları', 'non_revenue'], 'PAY' => ['Sistem ödeme kodları', 'payment'],
        ];
        foreach ($groups as $code => [$name, $type]) {
            DB::table('pms_transaction_code_groups')->updateOrInsert(['property_id' => $propertyId, 'code' => (string) $code], ['name' => $name, 'type' => $type, 'updated_at' => now()]);
        }

        // Fidelio "Department Codes" listesinin tamamı (database/data/fidelio/department-codes.tsv) — hepsi kullanımda.
        (new FidelioDepartmentCodeImporter)->import($propertyId, FidelioDepartmentCodeImporter::parse(
            file_get_contents(database_path('data/fidelio/department-codes.tsv'))
        ));

        $codeQuery = fn (array|string $codes) => DB::table('pms_transaction_codes')->where('property_id', $propertyId)->whereIn('code', (array) $codes);
        $groupId = fn (string $code) => (int) DB::table('pms_transaction_code_groups')->where('property_id', $propertyId)->where('code', $code)->value('id');

        // Gelir kovası ve yiyecek/içecek ayrımı gruba göre (istatistik ve Logo için)
        $codeQuery(['100', '102', '103', '105', '106', '107', '108', '109', '115', '116', '200', '201', '202'])->update(['revenue_bucket' => 'room']);
        foreach (['20' => 'food', '12' => 'food', '30' => 'beverage'] as $group => $kind) {
            DB::table('pms_transaction_codes')->where('group_id', $groupId($group))->update(['revenue_bucket' => $kind, 'food_beverage' => $kind]);
        }
        foreach (['50', '40', '55', '60', '75'] as $group) {
            DB::table('pms_transaction_codes')->where('group_id', $groupId($group))->whereNull('food_beverage')->update(['food_beverage' => 'other']);
        }
        $codeQuery(['105', '115'])->update(['is_no_show' => true]);
        $codeQuery(['100', '107'])->update(['arrangement_code' => 'ACC', 'revenue_bucket' => 'room']);   // Fidelio: ACC Accommodation Expense

        // Gelir dışı kodların tipi
        $codeQuery('800')->update(['type' => 'paid_out', 'revenue_bucket' => null]);
        $codeQuery(['879', '980', '990', '991', '993'])->update(['type' => 'internal', 'revenue_bucket' => null]);
        $codeQuery(['904', '992'])->update(['type' => 'deposit', 'revenue_bucket' => null]);

        // Ödeme kodları: ödeme türü ve döviz
        foreach ([
            'cash' => [['900'], ['902']], 'cheque' => [['901']], 'city_ledger' => [['903']],
            'card' => [['910'], ['911'], ['912'], ['913'], ['914'], ['915'], ['994', 'EUR'], ['995', 'USD'], ['996', 'GBP']],
            'bank_transfer' => [['920'], ['921', 'EUR'], ['922', 'USD'], ['923', 'GBP']],
            'foreign_cash' => [['950', 'USD'], ['951', 'EUR'], ['952', 'CHF'], ['953', 'SEK'], ['954', 'CAD'], ['955', 'NOK'], ['956', 'JPY'],
                ['957', 'SAR'], ['958', 'KWD'], ['959', 'AUD'], ['960', 'DKK'], ['961', 'GBP'], ['963', 'USD'], ['964', null], ['965', 'USD'],
                ['966', 'EUR'], ['967', 'GBP'], ['968', null]],
        ] as $kind => $list) {
            foreach ($list as $item) {
                $codeQuery($item[0])->update(['type' => 'payment', 'payment_kind' => $kind, 'currency_code' => $item[1] ?? null, 'revenue_bucket' => null, 'food_beverage' => null]);
            }
        }

        // Outlet'ler: Simphony EMC → Revenue Centers listesi (sıra = Simphony RVC numarası "#"). Dragon Rest Simphony'de yok (elle kayıt).
        // [outlet kodu, ad, tip, Simphony RVC no, departman kodları]. İlk kod varsayılan koddur.
        $range = fn (int $from, int $to) => array_map('strval', range($from, $to));
        $outlets = [
            ['HABE', 'La Habenera', 'restaurant', 1, ['395', '397', '398', '399', ...$range(544, 546), ...$range(440, 442), ...$range(500, 502), ...$range(610, 612), ...$range(620, 622)]],
            ['HIRO', "L'Hirondelle", 'restaurant', 2, [...$range(385, 388), ...$range(450, 452), '543']],
            ['CAFE', 'Cafe Rocks', 'restaurant', 3, $range(320, 328)],
            ['POOL', 'Pool Bar', 'bar', 4, $range(330, 338)],
            ['MINI', 'Minibar', 'minibar', 5, $range(350, 358)],
            ['RSVC', 'Room Service', 'room_service', 6, $range(340, 348)],
            ['BANQ', 'Banket', 'banquet', 7, $range(370, 378)],
            ['SPA', 'Spa Center', 'spa', 8, [...$range(513, 515), ...$range(526, 528), ...$range(547, 549)]],
            ['LAUN', 'Laundry', 'other', 9, $range(510, 512)],
            ['CASI', 'Casino', 'bar', 10, $range(670, 692)],
            ['KUAF', 'Kuaför', 'other', 11, $range(640, 642)],
            ['CTCH', 'Catch Seafood', 'restaurant', 12, $range(650, 661)],
            ['ONYX', 'Onyx', 'bar', 13, $range(480, 488)],
            ['BYBL', 'Byblos', 'restaurant', 14, $range(700, 722)],
            ['PATI', 'Rocks Pattiserie', 'restaurant', 15, $range(820, 832)],
            ['SIRA', 'Sıralı Et', 'restaurant', 16, $range(490, 495)],
            ['LYRA', 'Lyra', 'restaurant', 17, [...$range(810, 818), ...$range(117, 119)]],
            ['DRAG', 'Dragon Rest', 'restaurant', null, ['410', '411']],
        ];
        foreach ($outlets as $i => [$code, $name, $type, $rvc, $codes]) {
            DB::table('pms_outlets')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], [
                'name' => $name, 'type' => $type, 'source_system' => $rvc ? 'simphony' : 'manual', 'simphony_rvc_number' => $rvc,
                'sort' => $i + 1, 'is_active' => true, 'updated_at' => now(),
            ]);
            $outletId = (int) DB::table('pms_outlets')->where('property_id', $propertyId)->where('code', $code)->value('id');
            $codeQuery($codes)->update(['outlet_id' => $outletId]);
            DB::table('pms_outlets')->where('id', $outletId)->update(['default_transaction_code_id' => $codeQuery($codes[0])->value('id')]);
        }

        // Simphony satış kalemi → departman kodu (Fidelio 24.11.2026 kayıtlarından): kalem 1 yiyecek → FOOD kodu, kalem 2 içecek → BVRG kodu.
        // İndirimler kodun düzeltme koduna (Adjustment Dept) gider. Diğer kalemler (3, 4, 6, 7, 8) Simphony kalem adları gelince eklenecek.
        $foodBev = ['HABE' => ['395', '544'], 'HIRO' => ['385', '386'], 'CAFE' => ['320', '321'], 'POOL' => ['330', '331'], 'MINI' => ['350', '351'],
            'RSVC' => ['340', '341'], 'BANQ' => ['370', '371'], 'CASI' => ['670', '680'], 'CTCH' => ['650', '660'], 'ONYX' => ['480', '483'],
            'BYBL' => ['700', '710'], 'PATI' => ['820', '830'], 'SIRA' => ['490', '493'], 'LYRA' => ['810', '813']];
        foreach ($foodBev as $outletCode => $codes) {
            $outletId = (int) DB::table('pms_outlets')->where('property_id', $propertyId)->where('code', $outletCode)->value('id');
            foreach ($codes as $i => $no) {
                DB::table('pms_pos_revenue_mappings')->updateOrInsert(
                    ['property_id' => $propertyId, 'outlet_id' => $outletId, 'source_type' => 'sales_itemizer', 'source_code' => (string) ($i + 1)],
                    ['source_name' => $i === 0 ? 'Yiyecek' : 'İçecek', 'transaction_code_id' => $codeQuery($no)->value('id'), 'updated_at' => now()]);
            }
        }

        // Oda hesabı dışındaki ödemeler: Fidelio'daki sanal odalar (rezervasyonsuz hesap) ve ödeme kodları (IFC8 logu + 24.11.2026 kayıtları).
        $today = DB::table('pms_properties')->where('id', $propertyId)->value('current_business_date');
        $fa = function (string $no, string $name) use ($propertyId, $today) {
            if (! DB::table('pms_folios')->where('property_id', $propertyId)->where('folio_number', $no)->exists()) {
                DB::table('pms_folios')->insert(['property_id' => $propertyId, 'folio_number' => $no, 'type' => 'pseudo_room', 'name' => $name,
                    'window' => 1, 'opened_business_date' => $today, 'notes' => 'Simphony oda dışı ödemeleri (Fidelio sanal oda '.$no.')',
                    'created_at' => now(), 'updated_at' => now()]);
            }

            return (int) DB::table('pms_folios')->where('property_id', $propertyId)->where('folio_number', $no)->value('id');
        };
        foreach ([
            [601, 'Oda Hesabi', 'room_charge', null, null],
            [131, 'Nakit', 'cash', '900', ['9901', 'POS CASH']],
            [231, 'Kredi Karti', 'card', '910', ['9902', 'POS']],
            [402, 'Kart (402)', 'card', '910', ['9902', 'POS']],
            [404, 'Room Service Nakit', 'cash', '900', ['9903', 'ROOM SERVICE CASH']],
            [401, 'Personel Cari', 'city_ledger', '903', ['9401', 'PERSONEL CARI']],
        ] as [$number, $name, $kind, $payCode, $target]) {
            DB::table('pms_pos_tender_mappings')->updateOrInsert(['property_id' => $propertyId, 'outlet_id' => null, 'tender_number' => $number], [
                'tender_name' => $name, 'kind' => $kind, 'transaction_code_id' => $payCode ? $codeQuery($payCode)->value('id') : null,
                'target_folio_id' => $target ? $fa($target[0], $target[1]) : null, 'updated_at' => now(),
            ]);
        }

        // Simphony arayüzü (MF_ENH). Bugün Simphony #101 kaydı IFC8'e (192.168.200.11:5010) bağlı; RS PMS aynı portu dinler.
        // Paralel denemede Simphony'de ikinci kayıt (#102) açılır ve bu adresi gösterir (docs/04-simphony.md §4.1). Başta kapalıdır.
        if (! DB::table('pms_pos_interfaces')->where('property_id', $propertyId)->where('system', 'simphony')->exists()) {
            DB::table('pms_pos_interfaces')->insert([
                'property_id' => $propertyId, 'name' => 'Simphony MF_ENH', 'system' => 'simphony', 'protocol' => 'simphony_enhanced',
                'listen_host' => '0.0.0.0', 'listen_port' => 5010, 'allowed_ips' => json_encode(['192.168.200.9']), 'is_active' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Odalar — Fidelio Room Sections / Room Types / Rooms / Room Features (database/data/fidelio/*.tsv).
        // Blok: oda numarasının ilk hanesi (1xxx = A BLOK Deluxe, 2xxx = B BLOK Superior) — otel onayladı.
        $tsv = function (string $file): array {
            $rows = [];
            foreach (file(database_path("data/fidelio/{$file}"), FILE_IGNORE_NEW_LINES) as $i => $line) {
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $rows[] = explode("\t", $line);
            }
            array_shift($rows);   // başlık

            return $rows;
        };
        foreach ([['A', 'A BLOK'], ['B', 'B BLOK']] as [$code, $name]) {
            DB::table('pms_buildings')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], ['name' => $name, 'updated_at' => now()]);
        }
        $buildings = DB::table('pms_buildings')->where('property_id', $propertyId)->pluck('id', 'code');
        foreach (range(1, 7) as $floor) {
            DB::table('pms_floors')->updateOrInsert(['property_id' => $propertyId, 'code' => (string) $floor], ['name' => "{$floor}. kat", 'sort' => $floor, 'updated_at' => now()]);
        }
        $floors = DB::table('pms_floors')->where('property_id', $propertyId)->pluck('id', 'code');
        foreach ($tsv('room-types.tsv') as $i => [$code, $name, $quantity, $persons]) {
            DB::table('pms_room_types')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], [
                'name' => $name, 'short_name' => $code, 'max_occupancy' => (int) $persons, 'max_adults' => (int) $persons,
                'default_adults' => min(2, (int) $persons), 'beds' => (int) $persons, 'sort' => $i + 1, 'is_active' => true, 'updated_at' => now(),
            ]);
        }
        $types = DB::table('pms_room_types')->where('property_id', $propertyId)->pluck('id', 'code');
        foreach ($tsv('room-features.tsv') as [$code, $name]) {
            DB::table('pms_room_features')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], ['name' => $name, 'updated_at' => now()]);
        }
        $features = DB::table('pms_room_features')->where('property_id', $propertyId)->pluck('id', 'code');
        $featureRules = ['SV' => '/deniz/iu', 'MV' => '/dağ/iu', 'FB' => '/french|frc|fench/iu', 'TB' => '/twin/iu', 'NS' => '/no?n?smk|nonsmoking|nonsmke|nosmk/iu',
            'CN' => '/\\bcon(n)?\\b|conn/iu', 'EN' => '/engelli/iu'];
        $today = now()->toDateString();
        foreach ($tsv('rooms.tsv') as $i => [$number, $floor, $type, $description, $persons, $from, $until]) {
            DB::table('pms_rooms')->updateOrInsert(['property_id' => $propertyId, 'number' => $number], [
                'room_type_id' => $types[$type], 'floor_id' => $floors[$floor] ?? null, 'building_id' => $buildings[['1' => 'A', '2' => 'B'][$number[0]] ?? ''] ?? null,
                'description' => trim($description), 'max_occupancy' => (int) $persons, 'is_accessible' => (bool) preg_match('/engelli/iu', $description),
                'is_smoking' => false, 'sort' => $i + 1, 'is_active' => $until >= $today && $from <= $today, 'updated_at' => now(),
            ]);
            $roomId = DB::table('pms_rooms')->where('property_id', $propertyId)->where('number', $number)->value('id');
            foreach ($featureRules as $feature => $pattern) {
                if (preg_match($pattern, $description)) {
                    DB::table('pms_room_feature_room')->insertOrIgnore(['room_id' => $roomId, 'room_feature_id' => $features[$feature]]);
                }
            }
        }

        // Fidelio "Housekeeping" kodları: temizlik durumu (oda hk_status'una karşılık) ve işaretler (oda hk_flag).
        foreach ([['INS', 'Inspected', 'status', 'inspected'], ['CL', 'Clean', 'status', 'clean'], ['DI', 'Dirty', 'status', 'dirty'],
            ['TU', 'T.U', 'status', 'pickup'], ['DND', 'DND', 'flag', null], ['NL', 'No Luggage', 'flag', null], ['SO', 'Sleep Out', 'flag', null],
            ['NS', 'No Service', 'flag', null], ['DBL', 'Double Lock', 'flag', null]] as $i => [$code, $name, $kind, $mapsTo]) {
            DB::table('pms_hk_status_codes')->updateOrInsert(['property_id' => $propertyId, 'code' => $code],
                ['name' => $name, 'kind' => $kind, 'maps_to' => $mapsTo, 'sort' => $i + 1, 'is_active' => true, 'updated_at' => now()]);
        }
        // Döviz kuru kuralları (Fidelio Exchange Rates, 26.11.2026): Rate = TCMB döviz satış, Cash Rate = Rate − %5, Ratecode Rate = Rate.
        // Ekrandan değiştirilen kurala dokunulmaz.
        foreach (['EUR', 'USD', 'GBP', 'CHF', 'SEK', 'CAD', 'NOK', 'JPY', 'SAR', 'KWD', 'AUD', 'DKK'] as $currency) {
            DB::table('pms_exchange_rate_rules')->insertOrIgnore(['property_id' => $propertyId, 'currency_code' => $currency, 'base' => 'forex_sell',
                'rate_margin_pct' => 0, 'cash_margin_pct' => -5, 'ratecode_margin_pct' => 0, 'is_auto' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        // OOO / OOS sebepleri — Fidelio "Room Status Reason" listesi gelince değiştirilecek.
        foreach ([['OOO', 'Out of Order'], ['OOS', 'Out of Service']] as [$code, $name]) {
            DB::table('pms_reason_codes')->updateOrInsert(['property_id' => $propertyId, 'category' => 'room_status', 'code' => $code], ['name' => $name, 'is_active' => true, 'updated_at' => now()]);
        }

        // Yetkiler ve sistem yöneticisi rolü
        foreach ([['setup.outlets', 'Outlet tanımları', 'setup'], ['setup.transaction_codes', 'Departman kodları', 'setup'],
            ['reservations.view', 'Rezervasyon Navigatörü', 'reservations'], ['reservations.checkin', 'Giriş (check-in)', 'reservations'],
            ['reservations.edit', 'Rezervasyon açma ve düzenleme', 'reservations'], ['reservations.checkout', 'Çıkış (check-out)', 'reservations'],
            ['cashiering.billing', 'Folyo görüntüleme', 'cashiering'], ['cashiering.post', 'Kayıt, ödeme ve transfer', 'cashiering'],
            ['cashiering.reverse', 'Düzeltme (ters kayıt)', 'cashiering'],
            ['night_audit.view', 'Gece kapanışı ekranı', 'night_audit'], ['night_audit.run', 'Gece kapanışını çalıştırma', 'night_audit'],
            ['night_audit.override', 'Gece kapanışı kontrollerini amir onayıyla geçme', 'night_audit'],
            ['housekeeping.view', 'Kat hizmetleri ekranı', 'housekeeping'], ['housekeeping.status', 'Oda durumu değiştirme', 'housekeeping'],
            ['housekeeping.outage', 'Arızalı / hizmet dışı oda (OOO/OOS)', 'housekeeping'],
            ['setup.users', 'Kullanıcılar ve roller', 'setup'], ['cashiering.accounts', 'Rezervasyonsuz hesap (PM hesabı) açma', 'cashiering'], ['setup.options', 'Rezervasyon seçenekleri', 'setup'], ['setup.exchange_rates', 'Döviz kurları', 'setup']] as [$code, $name, $group]) {
            DB::table('pms_permissions')->updateOrInsert(['code' => $code], ['name' => $name, 'group' => $group, 'updated_at' => now()]);
        }
        DB::table('pms_roles')->updateOrInsert(['code' => 'admin'], ['name' => 'Sistem yöneticisi', 'updated_at' => now()]);
        $adminRole = (int) DB::table('pms_roles')->where('code', 'admin')->value('id');
        foreach (DB::table('pms_permissions')->pluck('id') as $permissionId) {
            DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $adminRole, 'permission_id' => $permissionId]);
        }

        // Fidelio "User Groups" → roller. Yetkiler görev tanımına göre BAŞLANGIÇ ÖNERİSİDİR; rol ekranından değiştirilebilir.
        // Bir role daha önce yetki verilmişse seeder ona dokunmaz (ekrandan yapılan değişiklik korunur).
        $front = ['reservations.view', 'reservations.edit', 'reservations.checkin', 'reservations.checkout', 'cashiering.billing', 'cashiering.post',
            'housekeeping.view', 'housekeeping.status'];
        $all = DB::table('pms_permissions')->pluck('code')->all();
        $groups = [
            'SUP' => ['Supervisor', $all], 'IT1' => ['IT Manager', $all],
            'FOM' => ['Front Office Manager', array_merge($front, ['cashiering.reverse', 'night_audit.view', 'night_audit.run', 'night_audit.override', 'setup.outlets', 'setup.transaction_codes'])],
            'FOS' => ['Front Office Supervisor', array_merge($front, ['cashiering.reverse', 'night_audit.view', 'night_audit.run', 'night_audit.override'])],
            'FOA' => ['Front Office Agent', $front], 'FOG' => ['Front Office Guest Relations', ['reservations.view', 'reservations.edit']],
            'HKS' => ['Housekeeping Supervisor', ['reservations.view', 'housekeeping.view', 'housekeeping.status', 'housekeeping.outage']], 'REA' => ['Reservations Agent', ['reservations.view', 'reservations.edit']],
            'RPT' => ['Reports Access Only', ['reservations.view', 'night_audit.view']], 'GMH' => ['Management', ['reservations.view', 'cashiering.billing', 'night_audit.view']],
            'SLM' => ['Sales Manager', ['reservations.view', 'reservations.edit']],
            'ACC' => ['Accounting', ['reservations.view', 'cashiering.billing', 'cashiering.post', 'cashiering.reverse', 'night_audit.view', 'setup.transaction_codes']],
            'AUD' => ['Night Auditor', array_merge($front, ['cashiering.reverse', 'night_audit.view', 'night_audit.run', 'night_audit.override'])],
            'IFC' => ['Fidelio Interface Group', []], 'SPA' => ['Spa Reception', ['reservations.view', 'cashiering.billing', 'cashiering.post']],
        ];
        $permissionIds = DB::table('pms_permissions')->pluck('id', 'code');
        // Kullanıcı ve rol yönetimi: Supervisor ve IT Manager (sonradan eklenen yetki; mevcut rollere de verilir)
        foreach (['SUP', 'IT1'] as $code) {
            if ($roleId = DB::table('pms_roles')->where('code', $code)->value('id')) {
                DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds['setup.users']]);
            }
        }
        foreach (['SUP', 'IT1', 'FOM'] as $code) {
            if ($roleId = DB::table('pms_roles')->where('code', $code)->value('id')) {
                DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds['setup.options']]);
            }
        }
        foreach (['SUP', 'IT1', 'FOM', 'ACC', 'AUD'] as $code) {
            if ($roleId = DB::table('pms_roles')->where('code', $code)->value('id')) {
                DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds['setup.exchange_rates']]);
            }
        }
        // PM hesabı açma: ön büro yönetimi, gece müdürü ve muhasebe
        foreach (['SUP', 'IT1', 'FOM', 'FOS', 'AUD', 'ACC'] as $code) {
            if ($roleId = DB::table('pms_roles')->where('code', $code)->value('id')) {
                DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds['cashiering.accounts']]);
            }
        }
        // Rezervasyonsuz hesap grupları (Fidelio Financial Account Groups) — diğer gruplar otelden gelince eklenecek
        DB::table('pms_folio_groups')->updateOrInsert(['property_id' => $propertyId, 'code' => 'PM'], ['name' => 'PM Accounts', 'updated_at' => now()]);
        foreach ($groups as $code => [$name, $permissions]) {
            DB::table('pms_roles')->updateOrInsert(['code' => $code], ['name' => $name, 'updated_at' => now()]);
            $roleId = (int) DB::table('pms_roles')->where('code', $code)->value('id');
            if (! DB::table('pms_role_permissions')->where('role_id', $roleId)->exists()) {
                foreach (array_unique($permissions) as $permission) {
                    DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
                }
            }
        }

        // Fidelio "Tax Details" (Rocks Hotel&Casino, Girne). Oran formülden alınır; Fidelio'daki ad bazı satırlarda formülle
        // uyuşmuyor (ör. "VAT 18 %" formülü x*(5/100)) — hesap formülle yapıldığı için formül esas, eski ad legacy_name'de saklanır.
        // [kod, oran, sıra, Fidelio adı, Fidelio departmanı, sıfır kaydet]
        foreach ([
            ['VAT0', 0, 3, 'VAT 0 %', 'VAT 0 %', true], ['VAT1', 1, 5, 'VAT 1 %', 'VAT 1 %', false], ['VAT5', 5, 1, 'VAT 18 %', 'VAT 5 %', false],
            ['VAT20', 20, null, 'VAT 20 %', 'VAT 20 %', false], ['VAT10', 10, 2, 'VAT 26 %', 'VAT 10 %', false], ['VAT16', 16, 4, 'VAT 8 %', 'VAT 16 %', false],
        ] as [$code, $rate, $sort, $legacyName, $department, $postZero]) {
            if (! DB::table('pms_taxes')->where('property_id', $propertyId)->where('code', $code)->exists()) {
                DB::table('pms_taxes')->insert([
                    'property_id' => $propertyId, 'code' => $code, 'name' => "KDV %{$rate}", 'calc_type' => 'percent', 'rate' => $rate,
                    'is_inclusive' => true, 'valid_from' => '2001-01-01', 'source' => 'fidelio', 'sort' => $sort, 'print_separately' => false,
                    'post_zero' => $postZero, 'legacy_name' => $legacyName, 'legacy_department' => $department, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Departman kodu → vergi. Fidelio'da her kodun penceresinde tarihli vergi var (ör. 100 Room, 107 Room Supplement:
        // "1.01.2001 – … VAT 5 %"). Kodların tamamı tek tek alınamadığı için otelin kararıyla: bütün gelir kodları %5;
        // adında oran yazan kodlar ("Others %0", "Others %16", "Others %20") o oranla. Spa, kuaför ve çamaşırhane kodları da %5
        // verildi, teyit edilecek (docs/fidelio/eslesme.md). Çamaşırhane ve transfer P4556 raporundan %16. Gelir dışı kodlar (ödeme, paid-out, iç, depozito, kur farkı) vergisizdir.
        // Daha önce vergisi bağlanmış koda dokunulmaz (ekrandan yapılan değişiklik korunur).
        $taxIds = DB::table('pms_taxes')->where('property_id', $propertyId)->whereNull('valid_to')->pluck('id', 'code');
        // Fidelio raporu P4556 "Department code totals … with tax group" (25.11.2026): 510 Laundry ve 520 Transfer → VAT 16 %.
        $byName = ['552' => 'VAT16', '553' => 'VAT16', '554' => 'VAT16', '555' => 'VAT20', '556' => 'VAT20', '557' => 'VAT20',
            '580' => 'VAT0', '581' => 'VAT0', '582' => 'VAT0',
            '510' => 'VAT16', '511' => 'VAT16', '512' => 'VAT16', '520' => 'VAT16', '521' => 'VAT16', '522' => 'VAT16'];
        $untaxed = ['550', '551', '880'];   // kur farkı
        $linked = DB::table('pms_transaction_code_taxes')->pluck('transaction_code_id')->all();
        $revenue = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('type', 'revenue')
            ->whereNotIn('code', $untaxed)->whereNotIn('id', $linked)->get(['id', 'code']);
        foreach ($revenue as $code) {
            DB::table('pms_transaction_code_taxes')->insert([
                'transaction_code_id' => $code->id, 'tax_id' => $taxIds[$byName[$code->code] ?? 'VAT5'], 'sequence' => 1, 'calc_base' => 'net',
                'valid_from' => '2001-01-01',   // Fidelio: "1.01.2001 – …"
            ]);
        }

        // Fidelio "Adjustment Dept": asıl kodun düzeltme kodu = ona bağlı ilk "DISC" (DIS kategorili) indirim kodu (100 → 102, 107 → 108).
        foreach (DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('is_discount', true)
            ->whereNotNull('discount_of_transaction_code_id')->orderByRaw('CAST(code AS UNSIGNED)')->get(['id', 'category', 'name', 'discount_of_transaction_code_id']) as $discount) {
            if (! str_starts_with(strtoupper($discount->name), 'REB') && ! str_contains(strtoupper($discount->name), 'REB.')) {
                DB::table('pms_transaction_codes')->where('id', $discount->discount_of_transaction_code_id)->whereNull('adjustment_transaction_code_id')
                    ->update(['adjustment_transaction_code_id' => $discount->id]);
            }
        }

        DB::table('pms_properties')->where('id', $propertyId)->update([
            'exchange_diff_transaction_code_id' => DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', '550')->value('id'),
        ]);
    }
}
