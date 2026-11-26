<?php

namespace Tests\Feature\Simphony;

use App\Domain\Pos\Simphony\ChargeRequest;
use App\Domain\Pos\Simphony\Frame;
use App\Domain\Pos\Simphony\FrameBuffer;
use App\Domain\Pos\Simphony\MessageHandler;
use App\Domain\Reservations\Models\Reservation;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Mesajlar gerçek IFC8 logundaki biçimdedir (docs/simphony-protokol.md); adlar ve numaralar değiştirilmiştir.
 * Örnek veri: oda 1102 Yılmaz (içeride), 2410 Müller (içeride), Rocks Patisserie RVC 15, Cafe Rocks RVC 3.
 */
class SimphonyProtocolTest extends DomainTestCase
{
    private const SRC = '000000006MF_ENH          ';

    private int $interface;

    private MessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoDataSeeder::class);
        $this->interface = (int) DB::table('pms_pos_interfaces')->where('system', 'simphony')->value('id');
        $this->handler = app(MessageHandler::class);
    }

    private function send(string $seq, string $type, array $fields, bool $retransmit = false): Frame
    {
        $raw = (new Frame(self::SRC, $seq, $retransmit, $type, $fields))->encode();
        if ($retransmit) {
            $raw = str_replace("\x1c{$seq} ", "\x1c{$seq}R", $raw);
            $raw = substr($raw, 0, -5).Frame::checksum(substr($raw, 1, strrpos($raw, "\x03"))).Frame::EOT;
        }

        return Frame::decode($this->handler->handleRaw($this->interface, $raw));
    }

    /** IFC_CHG_PST gövdesi: 16 satış, 16 indirim, 14 servis, 63 vergi + çek bilgileri (logdaki sıra). */
    private static function body(array $sales, array $discounts, string $check, int $rvc, string $amount): array
    {
        $pad = fn (array $v, int $n) => array_map(fn ($i) => number_format((float) ($v[$i] ?? 0), 2, '.', ''), range(1, $n));

        return [$amount, '16', ...$pad($sales, 16), '16', ...$pad($discounts, 16), '14', ...$pad([], 14),
            '63', ...$pad([1 => array_sum($sales) + array_sum($discounts)], 63), $check, '1010', '1', (string) $rvc, '2', '20261201', '11:36:46', '0.00'];
    }

    public function test_frame_checksum_matches_ifc8_log(): void
    {
        // Logdaki gerçek cevap: <SOH>000000006MF_ENH          <STX><FS>01 FO_87_DONE<FS>Check detail received<ETX>1041<EOT>
        $raw = (new Frame(self::SRC, '01', false, 'FO_87_DONE', ['Check detail received']))->encode();
        $this->assertSame("\x01".self::SRC."\x02\x1c01 FO_87_DONE\x1cCheck detail received\x031041\x04", $raw);
        $this->assertSame('6', Frame::decode($raw)->workstation());

        $buffer = new FrameBuffer;
        $this->assertSame([], $buffer->push("çöp\x01".substr($raw, 1, 20)));
        $this->assertSame([$raw, $raw], $buffer->push(substr($raw, 21).$raw));
        $this->assertTrue(FrameBuffer::isPing("\x01 0                \x02\x03\x04"));
    }

    public function test_version_and_room_query_and_details(): void
    {
        $this->assertSame('FO_87_VERSION', $this->send('01', 'FO_FI_VERSION', ['FOUX2 19.1.5', '10.05.23', 'Micros Systems Inc. Columbia MD USA'])->type);

        $list = $this->send('02', 'FO_FI_RES_QUERY', ['1102']);
        $this->assertSame('FO_87_RES_LIST', $list->type);
        $this->assertSame('1', $list->fields[1]);
        $id = trim($list->fields[2]);
        $this->assertSame((string) Reservation::whereHas('room', fn ($q) => $q->where('number', '1102'))->value('id'), $id);
        $this->assertStringStartsWith('1102   YILMAZ, AHMET', $list->fields[3]);   // Fidelio biçimi: SOYAD, AD (ASCII)
        $this->assertSame(' L', $list->fields[4]);

        $detail = $this->send('03', 'FO_FI_RES_DETAIL', [str_pad($id, 30)]);
        $this->assertSame(['10', 'Name           :    YILMAZ, AHMET'], [$detail->fields[0], rtrim($detail->fields[1])]);
        $this->assertSame('Ratecode       :    BAR', rtrim($detail->fields[9]));
        $this->assertSame(74, strlen($detail->fields[1]));

        $this->assertSame('FO_87_GST_DETAIL', $this->send('04', 'FO_FI_GST_DETAIL', [str_pad($id, 30)])->type);
        $this->assertSame(['No message found'], $this->send('05', 'FO_FI_GST_MESSAGE', [str_pad($id, 30)])->fields);
        $this->assertSame('FO_87_RES_LIST', $this->send('06', 'FO_FI_RES_QUERY', ['mul'])->type);   // soyadla
    }

    public function test_room_with_two_guests_lists_both_like_simphony_screens(): void
    {
        $res = Reservation::whereHas('room', fn ($q) => $q->where('number', '1102'))->firstOrFail();
        $res->profile->update(['title' => 'Bay', 'language_code' => 'tr', 'nationality_code' => 'TR']);
        $res->update(['guest_comments' => str_repeat('Sea view room requested, ', 2).'DENIZ']);
        $companion = DB::table('pms_profiles')->insertGetId(['property_id' => $this->property, 'type' => 'individual', 'display_name' => 'Akkaya, Melike',
            'last_name' => 'Akkaya', 'first_name' => 'Melike', 'title' => 'Bayan', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pms_reservation_guests')->insert(['reservation_id' => $res->id, 'profile_id' => $companion, 'role' => 'accompanying', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pms_guest_messages')->insert(['property_id' => $this->property, 'reservation_id' => $res->id, 'message' => 'Call reception', 'created_at' => now(), 'updated_at' => now()]);

        $list = $this->send('01', 'FO_FI_RES_QUERY', ['1102']);
        $this->assertSame('2', $list->fields[1]);
        $this->assertStringStartsWith('1102   YILMAZ, AHMET Bay', $list->fields[3]);
        $this->assertSame($res->id.'/'.$companion, trim($list->fields[5]));
        $this->assertStringStartsWith('1102   AKKAYA, MELIKE Bayan', $list->fields[6]);
        $this->assertSame('2', $this->send('02', 'FO_FI_RES_QUERY', ['akka'])->fields[1]);   // refakatçinin soyadıyla da bulunur

        $guest = $this->send('03', 'FO_FI_GST_DETAIL', [str_pad($res->id.'/'.$companion, 30)]);
        $this->assertSame('Name           :    AKKAYA, MELIKE Bayan', rtrim($guest->fields[1]));
        $main = $this->send('04', 'FO_FI_GST_DETAIL', [str_pad((string) $res->id, 30)]);
        $this->assertSame('Country        :    Turkey', rtrim($main->fields[6]));
        $this->assertSame('Language       :    T', rtrim($main->fields[9]));

        $detail = $this->send('05', 'FO_FI_RES_DETAIL', [str_pad((string) $res->id, 30)]);
        $this->assertSame('Remarks 2      :    DENIZ', rtrim($detail->fields[6]));
        $this->assertSame('Messages       :    1', rtrim($detail->fields[10]));
    }

    public function test_room_charge_dialog_posts_to_folio_by_department_codes(): void
    {
        $res = Reservation::whereHas('room', fn ($q) => $q->where('number', '1102'))->firstOrFail();
        $before = (float) $res->folios()->sum('balance');

        // 1) Misafir arama → seçim listesi
        $sel = $this->send('01', 'IFC_CHG_PST', ['1102', '601']);
        $this->assertSame('IFC_GST_SEL', $sel->type);
        $this->assertSame(['1102', 'IFC_CHG_PST', '1'], array_slice($sel->fields, 0, 3));
        $this->assertSame(str_pad('1102/YILMAZ', 40), $sel->fields[3]);

        // 2) Seçilen misafire kayıt: Rocks Patisserie (RVC 15) — 670 TL satış, 70 TL indirim → 600 TL
        $post = $this->send('02', 'IFC_CHG_PST', ['1102', str_pad('1102/YILMAZ', 40), 'S1', '601', ...self::body([1 => 670], [1 => -70], '3013', 15, '600.00')]);
        $this->assertSame(['N', 'P', str_pad('1102/YILMAZ', 30), '600.00', (string) $res->id], $post->fields);

        $postings = DB::table('pms_postings as p')->join('pms_transaction_codes as tc', 'tc.id', '=', 'p.transaction_code_id')
            ->where('p.source', 'simphony')->orderBy('p.id')->get(['tc.code', 'p.amount', 'p.reference', 'p.tax_amount']);
        $this->assertSame([['820', 670.0], ['821', -70.0]], $postings->map(fn ($p) => [$p->code, (float) $p->amount])->all());   // 820 → Adjustment 821
        $this->assertSame('CHK 3013 RVC 15', $postings[0]->reference);
        $this->assertSame(31.90, (float) $postings[0]->tax_amount);   // %5 dahil
        $this->assertSame($before + 600, (float) $res->folios()->sum('balance'));

        // 3) Fiş dökümü
        $done = $this->send('03', 'FO_FI_FINAL_TENDER', ['20261201', '11:36:46', '15', '3013', " \n  ROCKS PATISSERIE \n 1 PASTA  670.00 \n"]);
        $this->assertSame(['Check detail received'], $done->fields);
        $this->assertStringContainsString('ROCKS PATISSERIE', DB::table('pms_pos_postings')->where('check_number', '3013')->value('receipt_text'));
    }

    public function test_retransmit_and_duplicate_do_not_post_twice(): void
    {
        $args = ['1102', str_pad('1102/YILMAZ', 40), 'S1', '601', ...self::body([2 => 180], [], '4013', 4, '180.00')];
        $first = $this->send('07', 'IFC_CHG_PST', $args);
        $again = $this->send('07', 'IFC_CHG_PST', $args, retransmit: true);    // Simphony tekrarı (R)
        $dup = $this->send('08', 'IFC_CHG_PST', $args);                        // aynı ödeme yeni sıra no ile
        $this->assertSame($first->fields, $again->fields);
        $this->assertSame($first->fields, $dup->fields);
        $this->assertSame(1, DB::table('pms_postings')->where('source', 'simphony')->count());
        $this->assertSame(1, DB::table('pms_pos_postings')->count());
    }

    public function test_declines_unknown_room_wrong_selection_and_no_post(): void
    {
        $this->assertSame(['N', 'D', str_pad('Guest not found', 30)], $this->send('01', 'IFC_CHG_PST', ['999', '601'])->fields);

        $wrong = $this->send('02', 'IFC_CHG_PST', ['1102', str_pad('1102/BASKA', 40), 'S1', '601', ...self::body([1 => 100], [], '5001', 3, '100.00')]);
        $this->assertSame('D', $wrong->fields[1]);

        Reservation::whereHas('room', fn ($q) => $q->where('number', '2410'))->update(['room_charges_allowed' => false]);
        $noPost = $this->send('03', 'IFC_CHG_PST', ['2410', str_pad('2410/MULLER', 40), 'S1', '601', ...self::body([1 => 100], [], '5002', 3, '100.00')]);
        $this->assertSame(['N', 'D', str_pad('No post allowed', 30)], $noPost->fields);
        $this->assertSame(['room_not_found', 'no_post'], DB::table('pms_pos_postings')->orderBy('id')->pluck('reject_reason')->all());
        $this->assertSame(0, DB::table('pms_postings')->where('source', 'simphony')->count());
    }

    public function test_non_front_office_tender_posts_sale_and_payment_to_pseudo_room_like_fidelio(): void
    {
        // Fidelio 24.11.2026: CHK 15151781 Rocks Patisserie, 170 TL Visa → 9902 POS: 820 Rocks Pattiserie Food 170 + 910 Visa Card 170
        $reply = $this->send('01', 'IFC_CHG_PST', [' ', ' ', '231', ...self::body([1 => 200], [1 => -30], '15151781', 15, '170.00')]);
        $pos = DB::table('pms_folios')->where('folio_number', '9902')->first();
        $this->assertSame(['N', 'P', str_pad('9902/POS', 30), '170.00', (string) $pos->id], $reply->fields);

        $lines = DB::table('pms_postings as p')->join('pms_transaction_codes as tc', 'tc.id', '=', 'p.transaction_code_id')
            ->where('p.folio_id', $pos->id)->orderBy('p.id')->get(['tc.code', 'p.amount'])->map(fn ($l) => [$l->code, (float) $l->amount])->all();
        $this->assertSame([['820', 200.0], ['821', -30.0], ['910', -170.0]], $lines);
        $this->assertSame(0.0, (float) DB::table('pms_folios')->where('id', $pos->id)->value('balance'));   // satış = ödeme

        // Cafe Rocks nakit (131 → 9901 POS CASH, 900 Cash): kalem 2 içecek → 321 BVRG Cafe Rocks
        $this->send('02', 'IFC_CHG_PST', [' ', ' ', '131', ...self::body([2 => 370], [2 => -111], '331456', 3, '259.00')]);
        $cash = DB::table('pms_postings as p')->join('pms_transaction_codes as tc', 'tc.id', '=', 'p.transaction_code_id')
            ->join('pms_folios as f', 'f.id', '=', 'p.folio_id')->where('f.folio_number', '9901')->orderBy('p.id')->pluck('tc.code')->all();
        $this->assertSame(['321', '324', '900'], $cash);

        // Eşlemesi olmayan ödeme tipi: yalnız satış kaydı
        $other = $this->send('03', 'IFC_CHG_PST', [' ', ' ', '999', ...self::body([1 => 50], [], '11007', 11, '50.00')]);
        $this->assertSame('P', $other->fields[1]);
        $this->assertSame(3, DB::table('pms_pos_postings')->where('status', 'accepted')->count());
        $this->assertSame((int) DB::table('pms_outlets')->where('code', 'KUAF')->value('id'), (int) DB::table('pms_pos_postings')->orderByDesc('id')->value('outlet_id'));
    }

    public function test_split_payment_is_prorated_and_messages_are_logged(): void
    {
        // 1.000 TL'lik çekin 400 TL'si oda hesabına (bölünmüş ödeme)
        $this->send('01', 'IFC_CHG_PST', ['1102', str_pad('1102/YILMAZ', 40), 'S1', '601', ...self::body([1 => 600, 2 => 400], [], '6001', 3, '400.00')]);
        $amounts = DB::table('pms_postings')->where('source', 'simphony')->orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all();
        $this->assertSame([240.0, 160.0], $amounts);
        $this->assertSame(2, DB::table('pms_pos_messages')->count());   // gelen + giden
        $this->assertSame(['in', 'out'], DB::table('pms_pos_messages')->orderBy('id')->pluck('direction')->all());
    }

    public function test_charge_request_parser(): void
    {
        $frame = new Frame(self::SRC, '02', false, 'IFC_CHG_PST', ['1230', str_pad('1230/X', 40), 'S2', '601', ...self::body([1 => 670], [1 => -70], '3013', 3, '600.00')]);
        $req = ChargeRequest::fromFrame($frame);
        $this->assertSame(['selected', 2, 601, 600.0, 670.0, -70.0, '3013', 3, 2], [$req->kind, $req->selection, $req->tender, $req->amount,
            $req->sales[1], $req->discounts[1], $req->check, $req->rvc, $req->guests]);
    }
}
