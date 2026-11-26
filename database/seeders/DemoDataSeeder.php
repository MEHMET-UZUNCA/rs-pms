<?php

namespace Database\Seeders;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Inventory\Models\Room;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Reservations\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * GELİŞTİRME / EĞİTİM için örnek veri: oda tipleri, odalar, fiyat kodları, profiller ve farklı durumlarda rezervasyonlar
 * (içeride, bugün gelecek, bugün gidecek, çıkış yapmış, ileri tarihli, iptal) ve rezervasyonsuz hesaplar.
 * Gerçek Rocks Hotel verisi değildir; canlı sistemde çalışmaz. Kullanım: php artisan db:seed --class=DemoDataSeeder
 * Rezervasyonlar gerçek servislerle açılır (onay no, gece fiyatı, oda atama, giriş, folyo, kayıt, çıkış).
 */
class DemoDataSeeder extends Seeder
{
    public function run(ReservationService $reservations, PostingService $postings): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Örnek veri canlı sistemde yüklenmez.');
        }
        $p = (int) DB::table('pms_properties')->where('code', 'RCK')->value('id');
        $bd = CarbonImmutable::parse(DB::table('pms_properties')->where('id', $p)->value('current_business_date'));
        $now = now();

        // Oda tipleri ve odalar
        $types = [];
        foreach ([['DBL', 'Double', 2, 1], ['TWN', 'Twin', 2, 2], ['SUI', 'Suit', 3, 3], ['FAM', 'Aile odası', 4, 4]] as [$code, $name, $beds, $sort]) {
            DB::table('pms_room_types')->updateOrInsert(['property_id' => $p, 'code' => $code], ['name' => $name, 'beds' => $beds, 'sort' => $sort, 'updated_at' => $now]);
            $types[$code] = (int) DB::table('pms_room_types')->where('property_id', $p)->where('code', $code)->value('id');
        }
        foreach ([1 => ['DBL', 8], 2 => ['TWN', 8], 3 => ['SUI', 4], 4 => ['FAM', 4]] as $floor => [$type, $count]) {
            DB::table('pms_floors')->updateOrInsert(['property_id' => $p, 'code' => (string) $floor], ['name' => "{$floor}. kat", 'updated_at' => $now]);
            $floorId = DB::table('pms_floors')->where('property_id', $p)->where('code', (string) $floor)->value('id');
            for ($i = 1; $i <= $count; $i++) {
                DB::table('pms_rooms')->updateOrInsert(['property_id' => $p, 'number' => $floor.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                    ['room_type_id' => $types[$type], 'floor_id' => $floorId, 'updated_at' => $now]);
            }
        }

        // Kodlar
        $id = fn (string $table, string $code, array $values) => DB::table($table)->updateOrInsert(['property_id' => $p, 'code' => $code], $values + ['updated_at' => $now])
            ? (int) DB::table($table)->where('property_id', $p)->where('code', $code)->value('id') : 0;
        $gtd = $id('pms_reservation_types', 'GTD', ['name' => 'Garantili', 'deducts_inventory' => true]);
        $opt = $id('pms_reservation_types', 'OPT', ['name' => 'Opsiyon', 'deducts_inventory' => false]);
        $seg = ['IND' => $id('pms_market_segments', 'IND', ['name' => 'Bireysel']), 'TA' => $id('pms_market_segments', 'TA', ['name' => 'Acente']),
            'COR' => $id('pms_market_segments', 'COR', ['name' => 'Şirket']), 'CAS' => $id('pms_market_segments', 'CAS', ['name' => 'Casino'])];
        $src = $id('pms_source_codes', 'DIR', ['name' => 'Doğrudan']);
        $cancelReason = $id('pms_reason_codes', 'CXL', ['category' => 'cancellation', 'name' => 'Misafir iptal etti']);
        $room100 = (int) DB::table('pms_transaction_codes')->where('property_id', $p)->where('code', '100')->value('id');
        $rates = [];
        foreach (['BAR' => 'Günlük fiyat', 'CORP' => 'Şirket fiyatı', 'TA-HB' => 'Acente yarım pansiyon', 'CASINO' => 'Casino misafiri'] as $code => $name) {
            $rates[$code] = $id('pms_rate_codes', $code, ['name' => $name, 'currency_code' => 'TRY', 'room_transaction_code_id' => $room100]);
        }

        // Profiller
        $profile = function (string $type, string $display, array $extra = [], array $comms = []) use ($p, $now) {
            $existing = DB::table('pms_profiles')->where('property_id', $p)->where('display_name', $display)->value('id');
            $pid = $existing ?: DB::table('pms_profiles')->insertGetId(['property_id' => $p, 'type' => $type, 'display_name' => $display, 'created_at' => $now, 'updated_at' => $now] + $extra);
            if (! $existing) {
                foreach ($comms as $t => $v) {
                    DB::table('pms_profile_communications')->insert(['profile_id' => $pid, 'type' => $t, 'value' => $v]);
                }
            }

            return (int) $pid;
        };
        $agent = $profile('travel_agent', 'Örnek Turizm Acentesi', ['company_name' => 'Örnek Turizm Acentesi'], ['phone' => '+90 392 000 00 01']);
        $company = $profile('company', 'Örnek Holding A.Ş.', ['company_name' => 'Örnek Holding A.Ş.'], ['email' => 'rezervasyon@ornek.example']);
        $casino = $profile('company', 'Casino', ['company_name' => 'Casino']);
        $guest = fn (string $last, string $first, string $country, array $comms = []) => $profile('individual', "{$last}, {$first}",
            ['last_name' => $last, 'first_name' => $first, 'nationality_code' => $country], $comms);

        $rooms = Room::query()->where('property_id', $p)->orderBy('number')->get()->keyBy('number');
        $make = function (array $o) use ($reservations, $p, $types, $rates, $gtd, $opt, $seg, $src) {
            return $reservations->create(array_filter([
                'property_id' => $p, 'profile_id' => $o['guest'], 'room_type_id' => $types[$o['type']], 'rate_code_id' => $rates[$o['rate']],
                'arrival_date' => $o['arr']->toDateString(), 'departure_date' => $o['dep']->toDateString(), 'adults' => $o['adults'] ?? 2,
                'child_bands' => isset($o['child']) ? [1 => $o['child']] : [], 'reservation_type_id' => ($o['option'] ?? false) ? $opt : $gtd,
                'market_segment_id' => $seg[$o['seg'] ?? 'IND'], 'source_code_id' => $src, 'manual_amount' => $o['price'],
                'agent_profile_id' => $o['agent'] ?? null, 'company_profile_id' => $o['company'] ?? null, 'guest_comments' => $o['note'] ?? null,
            ], fn ($v) => $v !== null));
        };
        $setBd = fn (CarbonImmutable $d) => DB::table('pms_properties')->where('id', $p)->update(['current_business_date' => $d->toDateString()]);
        $code = fn (string $no) => TransactionCode::query()->where('property_id', $p)->where('code', $no)->firstOrFail();

        $stays = [
            // [geliş farkı, gece, oda, tip, fiyat kodu, fiyat, misafir, ek]
            [-3, 5, '101', 'DBL', 'BAR', 4200, $guest('Yılmaz', 'Ahmet', 'TR', ['phone' => '+90 533 000 00 11']), []],
            [-2, 2, '102', 'DBL', 'CORP', 3800, $guest('Smith', 'John', 'GB', ['email' => 'john.smith@example.com']), ['company' => $company, 'seg' => 'COR']],
            [-1, 4, '201', 'TWN', 'TA-HB', 5100, $guest('Ivanova', 'Elena', 'RU'), ['agent' => $agent, 'seg' => 'TA', 'adults' => 2, 'child' => 1]],
            [-4, 4, '301', 'SUI', 'CASINO', 0, $guest('Cohen', 'David', 'IL'), ['company' => $casino, 'seg' => 'CAS', 'adults' => 1, 'note' => 'Casino misafiri — oda ücretsiz']],
            [-1, 3, '202', 'TWN', 'BAR', 4600, $guest('Demir', 'Ayşe', 'TR'), []],
            [-2, 6, '401', 'FAM', 'TA-HB', 7400, $guest('Müller', 'Thomas', 'DE'), ['agent' => $agent, 'seg' => 'TA', 'adults' => 2, 'child' => 2]],
        ];
        $checkouts = [
            [-3, 3, '103', 'DBL', 'BAR', 4200, $guest('Kaya', 'Mehmet', 'TR'), []],
            [-2, 2, '203', 'TWN', 'CORP', 3800, $guest('Brown', 'Emma', 'GB'), ['company' => $company, 'seg' => 'COR']],
        ];

        // Geçmiş geliş günlerinde giriş: iş günü geçici olarak o güne alınır (yalnız örnek veri için).
        $inHouse = [];
        foreach ([...$stays, ...$checkouts] as $i => [$offset, $nights, $roomNo, $type, $rate, $price, $g, $extra]) {
            $arr = $bd->addDays($offset);
            $setBd($arr);
            $r = $make(['guest' => $g, 'type' => $type, 'rate' => $rate, 'price' => $price, 'arr' => $arr, 'dep' => $arr->addDays($nights)] + $extra);
            $reservations->assignRoom($r, $rooms[$roomNo]);
            $inHouse[] = [$reservations->checkIn($r->fresh()), $price, -$offset];
        }
        $setBd($bd);

        foreach ($inHouse as [$r, $price, $nightsSoFar]) {
            $folio = Folio::query()->where('reservation_id', $r->id)->where('window', 1)->firstOrFail();
            // Geçmiş her gece kendi iş gününde yazılır (gerçek gece kapanışındaki gibi), istatistik ve ADR doğru çıksın.
            for ($n = 0; $price > 0 && $n < $nightsSoFar; $n++) {
                $setBd(CarbonImmutable::parse($r->arrival_date)->addDays($n));
                $postings->charge($folio, $code('100'), $price, 1, 'Oda ücreti (örnek)');
            }
            $setBd($bd);
            $postings->charge($folio, $code('320'), 850, 1, 'Cafe Rocks (örnek)');
        }
        // Bugün çıkış yapanlar: ödeme alınır, çıkış yapılır
        foreach (array_slice($inHouse, count($stays)) as [$r]) {
            $folio = Folio::query()->where('reservation_id', $r->id)->where('window', 1)->firstOrFail();
            $postings->payment($folio, $code('910'), (float) $folio->fresh()->balance, 'Visa (örnek)');
            $reservations->checkOut($r->fresh());
        }

        // Bugün gelecekler (ikisinin odası atanmış) ve ileri tarihliler
        $arrivals = [
            [0, 3, '104', 'DBL', 'BAR', 4200, $guest('Öztürk', 'Zeynep', 'TR'), []],
            [0, 2, '302', 'SUI', 'CORP', 6900, $guest('Johnson', 'Mark', 'GB'), ['company' => $company, 'seg' => 'COR']],
            [0, 5, null, 'TWN', 'TA-HB', 5100, $guest('Petrov', 'Sergei', 'RU'), ['agent' => $agent, 'seg' => 'TA']],
            [0, 1, null, 'DBL', 'CASINO', 0, $guest('Levi', 'Noa', 'IL'), ['company' => $casino, 'seg' => 'CAS', 'adults' => 1]],
            [2, 4, null, 'FAM', 'BAR', 7400, $guest('Arslan', 'Can', 'TR'), ['adults' => 2, 'child' => 2]],
            [5, 7, null, 'DBL', 'TA-HB', 5100, $guest('Schmidt', 'Anna', 'DE'), ['agent' => $agent, 'seg' => 'TA']],
            [9, 3, null, 'SUI', 'CORP', 6900, $guest('Wilson', 'James', 'GB'), ['company' => $company, 'seg' => 'COR', 'option' => true]],
            [14, 2, null, 'TWN', 'BAR', 4600, $guest('Şahin', 'Elif', 'TR'), []],
        ];
        foreach ($arrivals as [$offset, $nights, $roomNo, $type, $rate, $price, $g, $extra]) {
            $arr = $bd->addDays($offset);
            $r = $make(['guest' => $g, 'type' => $type, 'rate' => $rate, 'price' => $price, 'arr' => $arr, 'dep' => $arr->addDays($nights)] + $extra);
            if ($roomNo) {
                $reservations->assignRoom($r, $rooms[$roomNo]);
            }
        }
        $cancelled = $make(['guest' => $guest('Aydın', 'Burak', 'TR'), 'type' => 'DBL', 'rate' => 'BAR', 'price' => 4200, 'arr' => $bd->addDays(3), 'dep' => $bd->addDays(5)]);
        $reservations->cancel($cancelled, $cancelReason);

        // Rezervasyonsuz hesaplar (Financial Account): biri açık, biri yarın açılacak
        foreach ([['FA-DEMO-1', 'house_account', 'Ön büro kasa hesabı', $bd->subDays(10), $bd->addDays(20)],
            ['FA-DEMO-2', 'event', 'Örnek Holding toplantısı', $bd->addDay(), $bd->addDays(2)]] as [$no, $type, $name, $from, $until]) {
            Folio::query()->updateOrCreate(['property_id' => $p, 'folio_number' => $no], [
                'type' => $type, 'name' => $name, 'window' => 1, 'valid_from' => $from->toDateString(), 'valid_until' => $until->toDateString(),
                'opened_business_date' => $from->lte($bd) ? $from->toDateString() : $bd->toDateString(),
            ]);
        }
        $postings->charge(Folio::query()->where('folio_number', 'FA-DEMO-1')->firstOrFail(), $code('630'), 300, 1, 'Otopark (örnek)');
    }
}
