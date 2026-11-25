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
            '10' => ['Oda', 'revenue'], '11' => ['Oda ek ücreti / bireysel oda', 'revenue'], '12' => ['Dragon Rest', 'revenue'],
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

        // Yetkiler ve sistem yöneticisi rolü
        foreach ([['setup.outlets', 'Outlet tanımları', 'setup']] as [$code, $name, $group]) {
            DB::table('pms_permissions')->updateOrInsert(['code' => $code], ['name' => $name, 'group' => $group, 'updated_at' => now()]);
        }
        DB::table('pms_roles')->updateOrInsert(['code' => 'admin'], ['name' => 'Sistem yöneticisi', 'updated_at' => now()]);
        $adminRole = (int) DB::table('pms_roles')->where('code', 'admin')->value('id');
        foreach (DB::table('pms_permissions')->pluck('id') as $permissionId) {
            DB::table('pms_role_permissions')->insertOrIgnore(['role_id' => $adminRole, 'permission_id' => $permissionId]);
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
        // verildi, teyit edilecek (docs/fidelio/eslesme.md). Gelir dışı kodlar (ödeme, paid-out, iç, depozito, kur farkı) vergisizdir.
        // Daha önce vergisi bağlanmış koda dokunulmaz (ekrandan yapılan değişiklik korunur).
        $taxIds = DB::table('pms_taxes')->where('property_id', $propertyId)->whereNull('valid_to')->pluck('id', 'code');
        $byName = ['552' => 'VAT16', '553' => 'VAT16', '554' => 'VAT16', '555' => 'VAT20', '556' => 'VAT20', '557' => 'VAT20',
            '580' => 'VAT0', '581' => 'VAT0', '582' => 'VAT0'];
        $untaxed = ['550', '551', '880'];   // kur farkı
        $linked = DB::table('pms_transaction_code_taxes')->pluck('transaction_code_id')->all();
        $revenue = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('type', 'revenue')
            ->whereNotIn('code', $untaxed)->whereNotIn('id', $linked)->get(['id', 'code']);
        foreach ($revenue as $code) {
            DB::table('pms_transaction_code_taxes')->insert([
                'transaction_code_id' => $code->id, 'tax_id' => $taxIds[$byName[$code->code] ?? 'VAT5'], 'sequence' => 1, 'calc_base' => 'net',
            ]);
        }

        DB::table('pms_properties')->where('id', $propertyId)->update([
            'exchange_diff_transaction_code_id' => DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', '550')->value('id'),
        ]);
    }
}
