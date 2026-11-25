<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rocks Hotel tanım verileri — Fidelio'nun canlı departman kodları ve outlet'leri (docs/fidelio/eslesme.md §15).
 * Kod numaraları ve adları Fidelio ile aynıdır. Vergi oranları, Simphony RVC numaraları ve Logo hesap kodları
 * otelden alınınca ayrıca girilecek (burada uydurulmaz). Birden fazla çalıştırılabilir; var olan kaydı günceller.
 */
class RocksHotelReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['TRY', 'TL', 'Türk Lirası', '₺', 1], ['EUR', 'EUR', 'Euro', '€', 2], ['USD', 'USD', 'ABD Doları', '$', 3], ['GBP', 'GBP', 'İngiliz Sterlini', '£', 4]] as [$code, $short, $name, $symbol, $sort]) {
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

        $groups = [
            'ROOM' => ['Konaklama ve pansiyon', 'revenue'], 'FNB' => ['Yiyecek içecek (outlet)', 'revenue'],
            'OTHER' => ['Diğer hizmetler', 'revenue'], 'PAY' => ['Ödemeler', 'payment'],
        ];
        $groupIds = [];
        foreach ($groups as $code => [$name, $type]) {
            DB::table('pms_transaction_code_groups')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], ['name' => $name, 'type' => $type, 'updated_at' => now()]);
            $groupIds[$code] = (int) DB::table('pms_transaction_code_groups')->where('property_id', $propertyId)->where('code', $code)->value('id');
        }

        // [kod, ad, grup, tip, gelir kovası, yiyecek/içecek, indirim mi, hangi kodun indirimi, no-show, ödeme türü, döviz]
        $codes = [
            ['100', 'Room', 'ROOM', 'revenue', 'room'], ['102', 'DISC Room', 'ROOM', 'revenue', 'room', null, true, '100'],
            ['105', 'Room No-Show', 'ROOM', 'revenue', 'room', null, false, null, true], ['107', 'Room Supplement', 'ROOM', 'revenue', 'room'],
            ['110', 'Pension Breakfast', 'ROOM', 'revenue', 'breakfast'], ['115', 'Casino Room No-Show', 'ROOM', 'revenue', 'room', null, false, null, true],
            ['130', 'Pension Dinner', 'ROOM', 'revenue', 'dinner'],
            ['510', 'Laundry', 'OTHER', 'revenue', 'laundry'], ['511', 'DISC Laundry', 'OTHER', 'revenue', 'laundry', null, true, '510'],
            ['513', 'SAUNA-MASSAGE', 'OTHER', 'revenue', 'spa'], ['514', 'DISC. SAUNA-MASSAGE', 'OTHER', 'revenue', 'spa', null, true, '513'],
            ['520', 'Transfer', 'OTHER', 'revenue', 'other'], ['550', 'Kur Farki', 'OTHER', 'revenue', 'other'],
            ['580', 'Others %0', 'OTHER', 'revenue', 'other'], ['630', 'Otopark', 'OTHER', 'revenue', 'other'],
            ['900', 'Cash', 'PAY', 'payment', null, null, false, null, false, 'cash'], ['903', 'City Ledger', 'PAY', 'payment', null, null, false, null, false, 'city_ledger'],
            ['910', 'Visa Card', 'PAY', 'payment', null, null, false, null, false, 'card'], ['920', 'Bank Payment', 'PAY', 'payment', null, null, false, null, false, 'bank_transfer'],
            ['950', 'Pay US Dollar', 'PAY', 'payment', null, null, false, null, false, 'foreign_cash', 'USD'],
            ['951', 'Pay Euro', 'PAY', 'payment', null, null, false, null, false, 'foreign_cash', 'EUR'],
        ];

        // Outlet'ler: [outlet kodu, ad, tip, yiyecek, içecek, ind. yiyecek, ind. içecek]
        $outlets = [
            ['CAFE', 'Cafe Rocks', 'restaurant', ['320', 'FOOD  Cafe Rocks'], ['321', 'BVRG Cafe Rocks'], ['322', 'DISC FOOD Cafe Rocks'], ['324', 'DISC BVRG Cafe Rocks']],
            ['POOL', 'Pool Bar', 'bar', ['330', 'FOOD  Pool Bar'], ['331', 'BVRG  Pool Bar'], ['332', 'DISC FOOD Pool Bar'], ['334', 'DISC BVRG Pool Bar']],
            ['RSVC', 'Room Service', 'room_service', ['340', 'FOOD Room Service'], ['341', 'BVRG Room Service'], ['342', 'DISC FOOD Room Service'], ['344', 'DISC BVRG Room Service']],
            ['MINI', 'Minibar', 'minibar', ['350', 'FOOD Minibar'], ['351', 'BVRG Minibar'], ['352', 'DISC FOOD Minibar'], ['354', 'DISC BVRG Minibar']],
            ['HIRO', "L'Hirondelle", 'restaurant', ['385', 'FOOD L HIRONDELLE'], ['386', 'BVRG L HIRONDELLE'], ['387', 'DISC L HIRONDELLE FOOD'], ['388', 'DISC L HIRONDELLE BEVRAGE']],
            ['SIRA', 'Sıralı Et', 'restaurant', ['490', 'FOOD Sirali Et'], ['493', 'BVRG Sirali Et'], ['491', 'Disc. FOOD Sirali Et'], ['494', 'DISC. BVRG Sirali Et']],
            ['CTCH', 'Catch Seafood', 'restaurant', ['650', 'Catch Seafood Food'], ['660', 'Catch Seafood Beverage'], ['651', 'Catch Seafood Food Disc.'], ['661', 'Catch Seafood Beverage Disc.']],
            ['CASI', 'Casino', 'bar', ['670', 'Casino Food'], ['680', 'Casino Beverage'], null, null],
            ['LYRA', 'Lyra', 'restaurant', ['810', 'FOOD Lyra'], ['813', 'BVRG  Lyra'], ['811', 'Disc. FOOD Lyra'], ['814', 'Disc. BVRG  Lyra']],
            ['PATI', 'Rocks Pattiserie', 'restaurant', ['820', 'Rocks Pattiserie Food'], ['830', 'Rocks Pattiserie Bvrg'], ['821', 'Rocks Pattiserie Food Disc.'], ['831', 'Rocks Pattiserie Bvrg Disc.']],
        ];

        $upsertCode = function (array $c, ?int $outletId = null) use ($propertyId, $groupIds) {
            [$code, $name, $group, $type] = $c;
            $values = [
                'group_id' => $groupIds[$group], 'name' => $name, 'type' => $type, 'revenue_bucket' => $c[4] ?? null, 'food_beverage' => $c[5] ?? null,
                'is_discount' => (bool) ($c[6] ?? false), 'is_no_show' => (bool) ($c[8] ?? false), 'payment_kind' => $c[9] ?? null,
                'currency_code' => $c[10] ?? null, 'outlet_id' => $outletId, 'manual_post_allowed' => true, 'is_active' => true, 'updated_at' => now(),
            ];
            if (isset($c[7])) {
                $values['discount_of_transaction_code_id'] = DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', $c[7])->value('id');
            }
            DB::table('pms_transaction_codes')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], $values);
        };

        foreach ($codes as $c) {
            $upsertCode($c);
        }

        foreach ($outlets as $i => [$code, $name, $type, $food, $bvrg, $discFood, $discBvrg]) {
            DB::table('pms_outlets')->updateOrInsert(['property_id' => $propertyId, 'code' => $code], ['name' => $name, 'type' => $type, 'source_system' => 'simphony', 'sort' => $i + 1, 'is_active' => true, 'updated_at' => now()]);
            $outletId = (int) DB::table('pms_outlets')->where('property_id', $propertyId)->where('code', $code)->value('id');
            $upsertCode([$food[0], $food[1], 'FNB', 'revenue', 'food', 'food'], $outletId);
            $upsertCode([$bvrg[0], $bvrg[1], 'FNB', 'revenue', 'beverage', 'beverage'], $outletId);
            if ($discFood) {
                $upsertCode([$discFood[0], $discFood[1], 'FNB', 'revenue', 'food', 'food', true, $food[0]], $outletId);
            }
            if ($discBvrg) {
                $upsertCode([$discBvrg[0], $discBvrg[1], 'FNB', 'revenue', 'beverage', 'beverage', true, $bvrg[0]], $outletId);
            }
            DB::table('pms_outlets')->where('id', $outletId)->update(['default_transaction_code_id' => DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', $food[0])->value('id')]);
        }

        // Catch Seafood'un ayrı "Reb." kodu ve Lyra'nın "OTHER" kodu
        $catch = (int) DB::table('pms_outlets')->where('property_id', $propertyId)->where('code', 'CTCH')->value('id');
        $lyra = (int) DB::table('pms_outlets')->where('property_id', $propertyId)->where('code', 'LYRA')->value('id');
        $upsertCode(['652', 'Catch Seafood Food Reb.', 'FNB', 'revenue', 'food', 'food', true, '650'], $catch);
        $upsertCode(['816', 'OTHER Lyra', 'FNB', 'revenue', 'other', 'other'], $lyra);

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

        DB::table('pms_properties')->where('id', $propertyId)->update([
            'exchange_diff_transaction_code_id' => DB::table('pms_transaction_codes')->where('property_id', $propertyId)->where('code', '550')->value('id'),
        ]);
    }
}
