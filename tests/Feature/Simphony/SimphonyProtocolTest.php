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
 * Örnek veri: oda 101 Yılmaz (içeride), 401 Müller (içeride), Rocks Patisserie RVC 15, Cafe Rocks RVC 3.
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

        $list = $this->send('02', 'FO_FI_RES_QUERY', ['101']);
        $this->assertSame('FO_87_RES_LIST', $list->type);
        $this->assertSame('1', $list->fields[1]);
        $id = trim($list->fields[2]);
        $this->assertSame((string) Reservation::whereHas('room', fn ($q) => $q->where('number', '101'))->value('id'), $id);
        $this->assertStringStartsWith('101    Yilmaz, Ahmet', $list->fields[3]);   // ASCII'ye çevrilmiş
        $this->assertSame(' L', $list->fields[4]);

        $detail = $this->send('03', 'FO_FI_RES_DETAIL', [str_pad($id, 30)]);
        $this->assertSame(['10', 'Name           :    Yilmaz, Ahmet'], [$detail->fields[0], rtrim($detail->fields[1])]);
        $this->assertSame('Ratecode       :    BAR', rtrim($detail->fields[9]));
        $this->assertSame(74, strlen($detail->fields[1]));

        $this->assertSame('FO_87_GST_DETAIL', $this->send('04', 'FO_FI_GST_DETAIL', [str_pad($id, 30)])->type);
        $this->assertSame(['No message found'], $this->send('05', 'FO_FI_GST_MESSAGE', [str_pad($id, 30)])->fields);
        $this->assertSame('FO_87_RES_LIST', $this->send('06', 'FO_FI_RES_QUERY', ['mul'])->type);   // soyadla
    }

    public function test_room_charge_dialog_posts_to_folio_by_department_codes(): void
    {
        $res = Reservation::whereHas('room', fn ($q) => $q->where('number', '101'))->firstOrFail();
        $before = (float) $res->folios()->sum('balance');

        // 1) Misafir arama → seçim listesi
        $sel = $this->send('01', 'IFC_CHG_PST', ['101', '601']);
        $this->assertSame('IFC_GST_SEL', $sel->type);
        $this->assertSame(['101', 'IFC_CHG_PST', '1'], array_slice($sel->fields, 0, 3));
        $this->assertSame(str_pad('101/YILMAZ', 40), $sel->fields[3]);

        // 2) Seçilen misafire kayıt: Rocks Patisserie (RVC 15) — 670 TL satış, 70 TL indirim → 600 TL
        $post = $this->send('02', 'IFC_CHG_PST', ['101', str_pad('101/YILMAZ', 40), 'S1', '601', ...self::body([1 => 670], [1 => -70], '3013', 15, '600.00')]);
        $this->assertSame(['N', 'P', str_pad('101/YILMAZ', 30), '600.00', (string) $res->id], $post->fields);

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
        $args = ['101', str_pad('101/YILMAZ', 40), 'S1', '601', ...self::body([2 => 180], [], '4013', 4, '180.00')];
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

        $wrong = $this->send('02', 'IFC_CHG_PST', ['101', str_pad('101/BASKA', 40), 'S1', '601', ...self::body([1 => 100], [], '5001', 3, '100.00')]);
        $this->assertSame('D', $wrong->fields[1]);

        Reservation::whereHas('room', fn ($q) => $q->where('number', '401'))->update(['room_charges_allowed' => false]);
        $noPost = $this->send('03', 'IFC_CHG_PST', ['401', str_pad('401/MULLER', 40), 'S1', '601', ...self::body([1 => 100], [], '5002', 3, '100.00')]);
        $this->assertSame(['N', 'D', str_pad('No post allowed', 30)], $noPost->fields);
        $this->assertSame(['room_not_found', 'no_post'], DB::table('pms_pos_postings')->orderBy('id')->pluck('reject_reason')->all());
        $this->assertSame(0, DB::table('pms_postings')->where('source', 'simphony')->count());
    }

    public function test_non_front_office_tender_is_recorded_as_sale(): void
    {
        DB::table('pms_pos_tender_mappings')->insert(['property_id' => $this->property, 'tender_number' => 231, 'tender_name' => 'Kredi Karti', 'kind' => 'card']);
        $reply = $this->send('01', 'IFC_CHG_PST', [' ', ' ', '231', ...self::body([1 => 500], [], '11007', 11, '500.00')]);
        $this->assertSame(['N', 'P', str_pad('POS Kredi Karti', 30), '500.00', '0'], $reply->fields);
        $sale = DB::table('pms_pos_postings')->first();
        $this->assertSame('accepted', $sale->status);
        $this->assertNull($sale->folio_id);
        $this->assertSame((int) DB::table('pms_outlets')->where('code', 'KUAF')->value('id'), (int) $sale->outlet_id);   // RVC 11 = Kuaför
    }

    public function test_split_payment_is_prorated_and_messages_are_logged(): void
    {
        // 1.000 TL'lik çekin 400 TL'si oda hesabına (bölünmüş ödeme)
        $this->send('01', 'IFC_CHG_PST', ['101', str_pad('101/YILMAZ', 40), 'S1', '601', ...self::body([1 => 600, 2 => 400], [], '6001', 3, '400.00')]);
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
