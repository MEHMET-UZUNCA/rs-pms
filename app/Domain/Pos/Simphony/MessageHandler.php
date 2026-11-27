<?php

namespace App\Domain\Pos\Simphony;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Models\Property;
use App\Domain\Pos\Models\Outlet;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Simphony MF_ENH mesajlarını işler ve cevabı üretir — docs/simphony-protokol.md.
 *  - Oda sorgusu ve detaylar: içerideki rezervasyonlardan.
 *  - Oda hesabı: misafir seçtirme → kayıt (satış kalemleri departman kodlarına; indirim, kodun düzeltme koduna) → fiş dökümü.
 *  - Oda dışı ödemeler: satış kaydı olarak saklanır (outlet cirosu ve mutabakat için).
 *  - Aynı sıra numarası + R bayrağı ya da aynı ödemenin tekrarı: ilk cevap aynen döner, çift kayıt olmaz.
 * Her mesaj (gelen ve giden) pms_pos_messages'a ham olarak yazılır.
 */
class MessageHandler
{
    /** @var array<string, array{seq:string, reply:string}> kasa → son cevap */
    private array $lastReply = [];

    public function __construct(private readonly PostingService $postings) {}

    /** Ham çerçeveyi işler, ham cevabı döner (ping için aynısı). */
    public function handleRaw(int $interfaceId, string $raw): string
    {
        $interface = DB::table('pms_pos_interfaces')->find($interfaceId);
        if (FrameBuffer::isPing($raw)) {
            DB::table('pms_pos_interfaces')->where('id', $interfaceId)->update(['last_heartbeat_at' => now()]);

            return $raw;
        }
        $this->log($interface, 'in', $raw);
        try {
            $frame = Frame::decode($raw);
        } catch (InvalidArgumentException $e) {
            $this->log($interface, 'in', $raw, 'failed', $e->getMessage());

            return '';
        }

        $ws = $frame->workstation();
        if ($frame->retransmit && isset($this->lastReply[$ws]) && $this->lastReply[$ws]['seq'] === $frame->sequence) {
            return $this->lastReply[$ws]['reply'];
        }

        try {
            $reply = $this->handle((int) $interface->property_id, $interfaceId, $frame);
        } catch (Throwable $e) {
            report($e);
            $reply = $frame->reply('FO_87_IFC_ERROR', ['PMS hatasi']);
        }
        $out = $reply->encode();
        $this->lastReply[$ws] = ['seq' => $frame->sequence, 'reply' => $out];
        $this->log($interface, 'out', $out);
        DB::table('pms_pos_interfaces')->where('id', $interfaceId)->update(['last_heartbeat_at' => now()]);

        return $out;
    }

    public function handle(int $propertyId, int $interfaceId, Frame $frame): Frame
    {
        return match ($frame->type) {
            'FO_FI_VERSION' => $frame->reply('FO_87_VERSION', ['FOUX2.0a', now()->format('Ymd'), 'RS PMS', config('app.version', '1.0.0')]),
            'FO_FI_RES_QUERY' => $this->resList($propertyId, $frame),
            'FO_FI_RES_DETAIL' => $this->resDetail($propertyId, $frame),
            'FO_FI_GST_DETAIL' => $this->guestDetail($propertyId, $frame),
            'FO_FI_GST_MESSAGE' => $frame->reply('FO_87_IFC_ERROR', ['No message found']),
            'FO_FI_GST_LOCATOR' => $frame->reply('FO_87_IFC_ERROR', ['Message/Locator not found']),
            'FO_FI_FINAL_TENDER' => $this->finalTender($propertyId, $frame),
            'IFC_CHG_PST' => $this->charge($propertyId, $interfaceId, $frame),
            default => $frame->reply('FO_87_IFC_ERROR', ['Unknown request '.$frame->type]),
        };
    }

    // ---------------------------------------------------------------- sorgular

    /**
     * Kasadaki "Select Guest Entry" listesi: odadaki her misafir bir satır (ana misafir ve refakatçiler, paylaşılan odada
     * diğer rezervasyonlar). Satır kimliği "rezervasyon no" ya da refakatçi için "rezervasyon no/profil no".
     */
    private function resList(int $propertyId, Frame $frame): Frame
    {
        $rows = $this->guestRows($this->inHouse($propertyId, trim($frame->field(0))));
        $fields = [str_pad('Room   Name                           Arrival/Departure     Payment', 82), (string) $rows->count()];
        foreach ($rows->take(20) as [$id, $r, $profile]) {
            $fields[] = str_pad($id, 30);
            $fields[] = str_pad(str_pad((string) $r->room?->number, 7).str_pad(Str::limit(self::guestName($profile), 30, ''), 31)
                .strtoupper($r->arrival_date->format('d-M-y')).' / '.strtoupper($r->departure_date->format('d-M-y')), 82);
            $fields[] = ' L';
        }

        return $frame->reply('FO_87_RES_LIST', $fields);
    }

    /** "Reservation Detail" penceresi (Simphony: Reservation Details). */
    private function resDetail(int $propertyId, Frame $frame): Frame
    {
        [$r, $profile] = $this->selected($propertyId, $frame->field(0));
        if (! $r) {
            return $frame->reply('FO_87_IFC_ERROR', ['Reservation not found']);
        }
        $group = $r->group_profile_id ? DB::table('pms_profiles')->where('id', $r->group_profile_id)->value('display_name') : '';
        $remarks = self::ascii(preg_replace('/\s+/', ' ', (string) $r->guest_comments));
        $lines = [
            'Name' => self::guestName($profile), 'Arrival' => $r->arrival_date->format('d.m.Y'), 'Departure' => $r->departure_date->format('d.m.Y'),
            'Payment Method' => '', 'Remarks 1' => mb_substr($remarks, 0, 50), 'Remarks 2' => mb_substr($remarks, 50, 50),
            'Adults / Kids' => $r->adults.' / '.$r->children, 'Group' => $group, 'Ratecode' => $r->rateCode?->code,
            'Messages' => (string) DB::table('pms_guest_messages')->where('reservation_id', $r->id)->whereNull('delivered_at')->count(),
        ];

        return $frame->reply('FO_87_RES_DETAIL', ['10', ...self::labelled($lines)]);
    }

    /** "Guest Profile" penceresi (Simphony: Guest Details). */
    private function guestDetail(int $propertyId, Frame $frame): Frame
    {
        [$r, $p] = $this->selected($propertyId, $frame->field(0));
        if (! $r) {
            return $frame->reply('FO_87_IFC_ERROR', ['Guest not found']);
        }
        $address = $p?->addresses()->orderByDesc('is_primary')->first();
        $comm = fn (string $type) => $p?->communications()->where('type', $type)->value('value');
        $country = $address?->country_code ?? $p?->nationality_code;
        $lines = [
            'Name' => self::guestName($p), 'Address 1' => $address?->line1, 'Address 2' => $address?->line2,
            'ZIP / City' => ($address?->postal_code ?? '').' / '.($address?->city ?? ''), 'State' => $address?->state,
            'Country' => $country ? (DB::table('pms_countries')->where('code', $country)->value('name_en') ?? $country) : '',
            'Phone' => $comm('phone') ?? $comm('mobile'), 'E-Mail' => $comm('email'),
            'Language' => self::languageLetter($p?->language_code),
            'VIP' => $p?->vip_level_id ? DB::table('pms_vip_levels')->where('id', $p->vip_level_id)->value('code') : '',
        ];

        return $frame->reply('FO_87_GST_DETAIL', ['10', ...self::labelled($lines)]);
    }

    /**
     * Rezervasyonlar → kasadaki misafir satırları: [kimlik, rezervasyon, profil].
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return Collection<int, array{0: string, 1: Reservation, 2: ?Profile}>
     */
    private function guestRows(Collection $reservations): Collection
    {
        return $reservations->flatMap(function (Reservation $r) {
            $rows = [[(string) $r->id, $r, $r->profile]];
            $companions = Profile::query()->join('pms_reservation_guests as g', 'g.profile_id', '=', 'pms_profiles.id')
                ->where('g.reservation_id', $r->id)->where('g.profile_id', '!=', $r->profile_id)->whereNull('g.checked_out_at')
                ->orderBy('g.id')->get(['pms_profiles.*']);
            foreach ($companions as $companion) {
                $rows[] = [$r->id.'/'.$companion->id, $r, $companion];
            }

            return $rows;
        })->values();
    }

    /** Kasadan gelen satır kimliği → [rezervasyon, profil]; "12/34" refakatçi misafirdir. */
    private function selected(int $propertyId, string $id): array
    {
        [$resId, $profileId] = array_pad(explode('/', trim($id), 2), 2, null);
        $r = $this->reservation($propertyId, (string) $resId);
        if (! $r) {
            return [null, null];
        }
        $profile = $profileId && DB::table('pms_reservation_guests')->where('reservation_id', $r->id)->where('profile_id', (int) $profileId)->exists()
            ? Profile::find((int) $profileId) : $r->profile;

        return [$r, $profile];
    }

    /** Fidelio adı: "SOYAD, AD Unvan" (ör. "OZCAN, MURAT SERDAR Bay"). */
    public static function guestName(?Profile $p): string
    {
        if (! $p) {
            return '';
        }
        $name = $p->last_name || $p->first_name
            ? trim(mb_strtoupper((string) $p->last_name, 'UTF-8').($p->first_name ? ', '.mb_strtoupper($p->first_name, 'UTF-8') : ''))
            : mb_strtoupper((string) $p->display_name, 'UTF-8');

        return self::ascii(trim($name.' '.($p->title ?? '')));
    }

    /** Fidelio dil kodu tek harf: T Türkçe, E İngilizce, G Almanca, R Rusça … */
    public static function languageLetter(?string $code): string
    {
        return match ($code) {
            null, '' => '',
            'de' => 'G',
            default => strtoupper($code[0]),
        };
    }

    // ---------------------------------------------------------------- oda hesabı ve ödemeler

    private function charge(int $propertyId, int $interfaceId, Frame $frame): Frame
    {
        $req = ChargeRequest::fromFrame($frame);
        $decline = fn (string $why) => $frame->reply('IFC_CHG_PST', ['N', 'D', str_pad(self::ascii($why), 30)]);

        if ($req->kind === 'lookup') {
            $guests = $this->inHouse($propertyId, $req->guestInput);
            if ($guests->isEmpty()) {
                return $decline('Guest not found');
            }

            return $frame->reply('IFC_GST_SEL', [$req->guestInput, 'IFC_CHG_PST', (string) $guests->count(),
                ...$guests->map(fn ($r) => str_pad($this->label($r), 40))->all()]);
        }

        // Aynı ödeme daha önce işlendiyse ilk cevap aynen döner.
        $existing = DB::table('pms_pos_postings')->where('idempotency_key', $req->idempotencyKey())->first();
        if ($existing) {
            if ($existing->status !== 'accepted') {
                return $decline((string) $existing->reject_reason);
            }
            $previous = $existing->reservation_id ? Reservation::with(['room:id,number', 'profile:id,display_name,last_name'])->find($existing->reservation_id) : null;

            return $frame->reply('IFC_CHG_PST', ['N', 'P', str_pad($previous ? $this->label($previous, 30) : 'POS', 30),
                self::money($req->amount), (string) ($existing->reservation_id ?? 0)]);
        }

        $outlet = Outlet::where('property_id', $propertyId)->where('simphony_rvc_number', $req->rvc)->first();
        $property = Property::findOrFail($propertyId);
        $base = [
            'property_id' => $propertyId, 'interface_id' => $interfaceId, 'outlet_id' => $outlet?->id, 'rvc_number' => $req->rvc,
            'check_number' => $req->check, 'workstation' => $frame->workstation(), 'employee_number' => $req->employee, 'covers' => $req->guests,
            'pos_business_date' => $req->date ? substr($req->date, 0, 4).'-'.substr($req->date, 4, 2).'-'.substr($req->date, 6, 2) : null,
            'business_date' => $property->current_business_date->toDateString(), 'total_amount' => $req->amount,
            'subtotals' => json_encode(['tender' => $req->tender, 'sales' => array_filter($req->sales), 'discounts' => array_filter($req->discounts),
                'services' => array_filter($req->services), 'taxes' => array_filter($req->taxes), 'serving_period' => $req->period,
                'time' => $req->time, 'previous_payment' => $req->previousPayment]),
            'idempotency_key' => $req->idempotencyKey(), 'created_at' => now(),
        ];

        if ($req->kind === 'non_front_office') {
            return $this->nonFrontOffice($propertyId, $frame, $req, $outlet, $base, $decline);
        }

        $guests = $this->inHouse($propertyId, $req->guestInput);
        $reservation = $guests->values()->get($req->selection - 1);
        $reject = function (string $reason, string $message) use ($base, $reservation, $decline) {
            DB::table('pms_pos_postings')->insert($base + ['room_number' => (string) ($reservation?->room?->number ?? '-'),
                'reservation_id' => $reservation?->id, 'status' => 'rejected', 'reject_reason' => $reason]);

            return $decline($message);
        };
        if (! $reservation || $this->label($reservation) !== rtrim($req->selectionText)) {
            return $reject('room_not_found', 'Guest not found');
        }
        if (! $reservation->room_charges_allowed) {
            return $reject('no_post', 'No post allowed');
        }
        $folio = Folio::where('reservation_id', $reservation->id)->where('window', 1)->where('status', 'open')->first();
        if (! $folio) {
            return $reject('not_in_house', 'No open folio');
        }
        if ($reservation->credit_limit !== null && (float) $folio->balance + $req->amount > (float) $reservation->credit_limit) {
            return $reject('credit_limit', 'Credit limit exceeded');
        }

        try {
            DB::transaction(function () use ($base, $reservation, $folio, $req, $outlet, $propertyId) {
                $posId = DB::table('pms_pos_postings')->insertGetId($base + ['room_number' => (string) $reservation->room?->number,
                    'reservation_id' => $reservation->id, 'folio_id' => $folio->id, 'status' => 'accepted']);
                foreach ($this->lines($propertyId, $outlet, $req) as $n => [$code, $amount, $label]) {
                    $this->postings->charge($folio, $code, $amount, 1, null, [
                        'idempotency_key' => $req->idempotencyKey().':'.$n, 'source' => 'simphony', 'outlet_id' => $outlet?->id,
                        'reference' => 'CHK '.$req->check.' RVC '.$req->rvc, 'pos_check_number' => $req->check, 'pos_posting_id' => $posId,
                    ]);
                }
            });
        } catch (PostingException $e) {
            $closed = str_contains($e->getMessage(), 'İş günü') || str_contains($e->getMessage(), 'kapalı');

            return $reject($closed ? 'closed_day' : 'posting_error', $closed ? 'Business day closed' : Str::limit($e->getMessage(), 30, ''));
        }

        return $frame->reply('IFC_CHG_PST', ['N', 'P', str_pad($this->label($reservation, 30), 30), self::money($req->amount), (string) $reservation->id]);
    }

    /**
     * Oda hesabı dışındaki ödeme (nakit, kart, personel cari): Fidelio'daki gibi ödeme tipinin sanal hesabına satış kalemleri ve
     * ödeme kodu birlikte yazılır (ör. 231 → 9902 POS: 820 Rocks Pattiserie Food + 910 Visa Card). Eşleme yoksa yalnız satış kaydı tutulur.
     */
    private function nonFrontOffice(int $propertyId, Frame $frame, ChargeRequest $req, ?Outlet $outlet, array $base, \Closure $decline): Frame
    {
        $tender = DB::table('pms_pos_tender_mappings')->where('property_id', $propertyId)->where('tender_number', $req->tender)
            ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outlet?->id))->orderByRaw('CASE WHEN outlet_id IS NULL THEN 1 ELSE 0 END')->first();
        $folio = $tender?->target_folio_id ? Folio::find($tender->target_folio_id) : null;
        $label = $folio ? $folio->folio_number.'/'.self::ascii($folio->name) : 'POS '.self::ascii($tender?->tender_name ?? (string) $req->tender);

        if (! $folio || ! $tender->transaction_code_id) {
            DB::table('pms_pos_postings')->insert($base + ['room_number' => 'POS', 'status' => 'accepted']);

            return $frame->reply('IFC_CHG_PST', ['N', 'P', str_pad(Str::limit($label, 30, ''), 30), self::money($req->amount), '0']);
        }

        try {
            DB::transaction(function () use ($base, $folio, $tender, $req, $outlet, $propertyId) {
                $posId = DB::table('pms_pos_postings')->insertGetId($base + ['room_number' => $folio->folio_number, 'folio_id' => $folio->id, 'status' => 'accepted']);
                $attributes = fn (string $n) => ['idempotency_key' => $req->idempotencyKey().':'.$n, 'source' => 'simphony', 'outlet_id' => $outlet?->id,
                    'reference' => 'CHK '.$req->check.' RVC '.$req->rvc, 'pos_check_number' => $req->check, 'pos_posting_id' => $posId];
                foreach ($this->lines($propertyId, $outlet, $req) as $n => [$code, $amount]) {
                    $this->postings->charge($folio, $code, $amount, 1, null, $attributes((string) $n));
                }
                if (abs($req->amount) >= 0.005) {
                    $this->postings->payment($folio, TransactionCode::findOrFail($tender->transaction_code_id), $req->amount, null, $attributes('pay'));
                }
            });
        } catch (PostingException $e) {
            DB::table('pms_pos_postings')->insert($base + ['room_number' => $folio->folio_number, 'status' => 'rejected', 'reject_reason' => 'posting_error']);

            return $decline(Str::limit($e->getMessage(), 30, ''));
        }

        return $frame->reply('IFC_CHG_PST', ['N', 'P', str_pad(Str::limit($label, 30, ''), 30), self::money($req->amount), (string) $folio->id]);
    }

    /**
     * Ödemeyi departman kodlarına böler. Satış kalemi i → eşleme (outlet, sales_itemizer, i) ya da outlet'in varsayılan kodu;
     * indirim kalemi i → eşleme (outlet, discount, i) ya da satış kodunun düzeltme kodu (Fidelio "Adjustment Dept"); servis → eşleme.
     * Ödeme çekin tamamı değilse (bölünmüş ödeme) tutarlar oranlanır; kuruş farkı son satıra.
     *
     * @return list<array{0:TransactionCode,1:float,2:string}>
     */
    private function lines(int $propertyId, ?Outlet $outlet, ChargeRequest $req): array
    {
        $map = DB::table('pms_pos_revenue_mappings')->where('property_id', $propertyId)
            ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outlet?->id))
            ->orderByRaw('CASE WHEN outlet_id IS NULL THEN 1 ELSE 0 END')->get()
            ->groupBy(fn ($m) => $m->source_type.':'.$m->source_code)->map(fn ($g) => (int) $g->first()->transaction_code_id);
        $default = $outlet?->default_transaction_code_id;
        $find = fn (?int $id) => $id ? TransactionCode::find($id) : null;
        $raw = [];
        foreach ($req->sales as $i => $amount) {
            if (abs($amount) < 0.005) {
                continue;
            }
            $code = $find($map["sales_itemizer:{$i}"] ?? $default) ?? throw new PostingException("RVC {$req->rvc}: satış kalemi {$i} için departman kodu yok.");
            $raw[] = [$code, $amount, 'Satış '.$i];
            $disc = $req->discounts[$i] ?? 0.0;
            if (abs($disc) >= 0.005) {
                $raw[] = [$find($map["discount:{$i}"] ?? $code->adjustment_transaction_code_id) ?? $code, $disc, 'İndirim '.$i];
            }
        }
        foreach ($req->discounts as $i => $disc) {
            if (abs($disc) >= 0.005 && abs($req->sales[$i] ?? 0) < 0.005) {
                $raw[] = [$find($map["discount:{$i}"] ?? $default) ?? throw new PostingException("RVC {$req->rvc}: indirim {$i} için kod yok."), $disc, 'İndirim '.$i];
            }
        }
        foreach ($req->services as $i => $svc) {
            if (abs($svc) >= 0.005) {
                $raw[] = [$find($map["service_charge:{$i}"] ?? $default) ?? throw new PostingException("RVC {$req->rvc}: servis {$i} için kod yok."), $svc, 'Servis '.$i];
            }
        }

        $total = array_sum(array_column($raw, 1));
        if ($raw === [] || abs($total - $req->amount) < 0.005 || abs($total) < 0.005) {
            return $raw;
        }
        $ratio = $req->amount / $total;
        $done = 0.0;
        foreach ($raw as $k => $line) {
            $raw[$k][1] = $k === array_key_last($raw) ? round($req->amount - $done, 2) : round($line[1] * $ratio, 2);
            $done += $raw[$k][1];
        }

        return $raw;
    }

    private function finalTender(int $propertyId, Frame $frame): Frame
    {
        [$date, $time, $rvc, $check] = [trim($frame->field(0)), trim($frame->field(1)), (int) $frame->field(2), trim($frame->field(3))];
        DB::table('pms_pos_postings')->where('property_id', $propertyId)->where('rvc_number', $rvc)->where('check_number', $check)
            ->whereNull('receipt_text')->orderByDesc('id')->limit(1)
            ->update(['receipt_text' => str_replace("\n", PHP_EOL, rtrim($frame->field(4)))]);

        return $frame->reply('FO_87_DONE', ['Check detail received']);
    }

    // ---------------------------------------------------------------- yardımcılar

    /** İçerideki rezervasyonlar: giriş rakamsa oda numarası, değilse soyad. */
    private function inHouse(int $propertyId, string $input): Collection
    {
        $input = trim($input);
        if ($input === '') {
            return collect();
        }

        return Reservation::where('pms_reservations.property_id', $propertyId)->where('status', 'in_house')
            ->with(['room:id,number', 'profile', 'rateCode:id,code'])
            ->when(ctype_digit($input),
                fn ($q) => $q->whereHas('room', fn ($r) => $r->where('number', $input)),
                fn ($q) => $q->where(fn ($any) => $any
                    ->whereHas('profile', fn ($p) => $p->where(fn ($w) => $w->where('last_name', 'like', $input.'%')->orWhere('display_name', 'like', $input.'%')))
                    ->orWhereIn('pms_reservations.id', DB::table('pms_reservation_guests as g')->join('pms_profiles as gp', 'gp.id', '=', 'g.profile_id')
                        ->where(fn ($w) => $w->where('gp.last_name', 'like', $input.'%')->orWhere('gp.display_name', 'like', $input.'%'))->select('g.reservation_id'))))
            ->orderBy('id')->get();
    }

    private function reservation(int $propertyId, string $id): ?Reservation
    {
        return Reservation::where('property_id', $propertyId)->with(['profile', 'rateCode'])->find((int) trim($id));
    }

    /** "1327/BALARISI" — Fidelio'nun seçim listesi ve kabul cevabındaki biçim. */
    private function label(Reservation $r, int $max = 40): string
    {
        $last = $r->profile?->last_name ?: Str::before((string) $r->profile?->display_name, ',');

        return Str::limit($r->room?->number.'/'.mb_strtoupper(self::ascii($last)), $max, '');
    }

    /** @return list<string> "Etiket          :    değer" satırları (74 hane) */
    private static function labelled(array $lines): array
    {
        $out = [];
        foreach ($lines as $label => $value) {
            $out[] = str_pad(str_pad($label, 15).':    '.self::ascii((string) $value), 74);
        }

        return $out;
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** Simphony ASCII konuşur: Türkçe harfler ASCII'ye çevrilir (Ş → S, ı → i). */
    public static function ascii(?string $value): string
    {
        return Str::ascii((string) $value, 'tr');
    }

    private function log(object $interface, string $direction, string $raw, string $status = 'processed', ?string $error = null): void
    {
        $type = preg_match('/\x1c[ \d]{2}[ R]\s*([A-Z0-9_]+)/', $raw, $m) ? $m[1] : 'unknown';
        DB::table('pms_pos_messages')->insert([
            'property_id' => $interface->property_id, 'interface_id' => $interface->id, 'direction' => $direction,
            'message_type' => Str::limit($type, 20, ''), 'raw' => $raw, 'status' => $status, 'error' => $error, 'created_at' => now(),
        ]);
    }
}
