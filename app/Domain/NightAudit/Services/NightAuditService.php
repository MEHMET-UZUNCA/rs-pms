<?php

namespace App\Domain\NightAudit\Services;

use App\Domain\Cashiering\Models\Folio;
use App\Domain\Cashiering\Services\PostingService;
use App\Domain\Core\Models\Property;
use App\Domain\NightAudit\Exceptions\NightAuditException;
use App\Domain\Rates\Models\RateCode;
use App\Domain\Rates\Models\TransactionCode;
use App\Domain\Rates\Services\AvailabilityService;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Models\ReservationNight;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gece kapanışı (Fidelio Night Audit sırası):
 *  1. Kontroller   — çıkışı geçmiş içerideki misafir (engeller), açık kasa (engeller), gelmeyen misafir (uyarı).
 *  2. No-show      — bugün gelmesi gerekip gelmeyen kesin rezervasyonlar no-show olur.
 *  3. Oda ücretleri — içerideki her rezervasyonun bu geceki fiyatı folyoya yazılır; fiyata dahil paket elemanları
 *                    (kahvaltı, akşam yemeği) kendi kodlarına ayrılır, kalan oda koduna yazılır. Ücretsiz/ev kullanımında kayıt yok.
 *  4. İstatistik   — günlük istatistik (toplam ve oda tipi), işlem kodu bazında gelir, defter dengesi.
 *  5. İş günü      — bugün kapanır, ertesi gün açılır.
 * Her adım kaydedilir; yarıda kalan kapanış tekrar çalıştırıldığında çift kayıt üretmez (idempotency_key).
 */
class NightAuditService
{
    public const STEPS = ['checks', 'no_shows', 'room_charges', 'statistics', 'advance_date'];

    public function __construct(private readonly PostingService $postings, private readonly AvailabilityService $availability) {}

    /**
     * Kapanış öncesi kontrol listesi (çalıştırmadan): ekranda gösterilir. Her kontrol: önem (block/warn) ve ilgili rezervasyonlar.
     *
     * @return array<string, array{severity:string, items:Collection}>
     */
    public function preview(int $propertyId): array
    {
        $bd = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();
        $res = fn () => Reservation::query()->where('property_id', $propertyId)->with(['profile:id,display_name', 'room:id,number']);

        return [
            'departures_pending' => ['severity' => 'block', 'items' => $res()->where('status', 'in_house')->where('departure_date', '<=', $bd)->where('is_day_use', false)->get()],
            'open_cashier_shifts' => ['severity' => 'block', 'items' => DB::table('pms_cashier_shifts')->where('property_id', $propertyId)->where('status', 'open')->where('business_date', '<=', $bd)->get()],
            'arrivals_pending' => ['severity' => 'warn', 'items' => $res()->whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', $bd)->get()],
        ];
    }

    public function run(int $propertyId, ?int $userId = null, array $acknowledged = []): int
    {
        $property = Property::query()->findOrFail($propertyId);
        $bd = $property->current_business_date->toDateString();

        $run = DB::table('pms_night_audit_runs')->where('property_id', $propertyId)->where('business_date', $bd)->first();
        if ($run && $run->status === 'completed') {
            throw new NightAuditException("{$bd} iş günü zaten kapatılmış.");
        }
        $runId = $run->id ?? DB::table('pms_night_audit_runs')->insertGetId([
            'property_id' => $propertyId, 'business_date' => $bd, 'status' => 'running', 'trigger' => 'manual', 'run_by' => $userId,
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pms_night_audit_runs')->where('id', $runId)->update(['status' => 'running', 'updated_at' => now()]);

        foreach (self::STEPS as $i => $step) {
            $done = DB::table('pms_night_audit_steps')->where('run_id', $runId)->where('code', $step)->where('status', 'done')->exists();
            if ($done) {
                continue;
            }
            DB::table('pms_night_audit_steps')->updateOrInsert(['run_id' => $runId, 'code' => $step], ['sequence' => $i + 1, 'status' => 'running', 'started_at' => now()]);
            DB::table('pms_night_audit_runs')->where('id', $runId)->update(['current_step' => $step]);

            $result = match ($step) {
                'checks' => $this->checks($runId, $property, $bd, $acknowledged),
                'no_shows' => $this->noShows($property, $bd, $userId),
                'room_charges' => $this->roomCharges($property, $bd, $userId),
                'statistics' => $this->statistics($property, $bd),
                'advance_date' => $this->advance($property, $bd, $runId, $userId),
            };

            DB::table('pms_night_audit_steps')->where('run_id', $runId)->where('code', $step)
                ->update(['status' => 'done', 'finished_at' => now(), 'result' => json_encode($result)]);
        }

        DB::table('pms_night_audit_runs')->where('id', $runId)->update([
            'status' => 'completed', 'finished_at' => now(), 'current_step' => null, 'updated_at' => now(),
            'report_pack' => json_encode($this->reportPack($propertyId, $bd)),
        ]);

        return $runId;
    }

    private function checks(int $runId, Property $property, string $bd, array $acknowledged): array
    {
        DB::table('pms_night_audit_checks')->where('run_id', $runId)->delete();
        $checks = [
            'departures_pending' => ['block', Reservation::query()->where('property_id', $property->id)->where('status', 'in_house')
                ->where('departure_date', '<=', $bd)->where('is_day_use', false)->pluck('confirmation_number')->all()],
            'open_cashier_shifts' => ['block', DB::table('pms_cashier_shifts')->where('property_id', $property->id)->where('status', 'open')
                ->where('business_date', '<=', $bd)->pluck('id')->all()],
            'arrivals_pending' => ['warn', Reservation::query()->where('property_id', $property->id)->whereIn('status', ['confirmed', 'tentative'])
                ->where('arrival_date', $bd)->pluck('confirmation_number')->all()],
        ];

        $blocking = [];
        foreach ($checks as $code => [$severity, $items]) {
            $ack = $acknowledged[$code] ?? null;
            DB::table('pms_night_audit_checks')->insert([
                'run_id' => $runId, 'code' => $code, 'severity' => $severity, 'items_count' => count($items), 'items' => json_encode($items),
                'acknowledged_reason' => $ack, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($severity === 'block' && $items && ! $ack) {
                $blocking[] = $code;
            }
        }
        DB::table('pms_night_audit_runs')->where('id', $runId)->update(['exceptions_count' => count($blocking)]);
        if ($blocking) {
            DB::table('pms_night_audit_runs')->where('id', $runId)->update(['status' => 'held']);
            DB::table('pms_night_audit_steps')->where('run_id', $runId)->where('code', 'checks')->update(['status' => 'failed', 'error' => implode(', ', $blocking)]);
            throw new NightAuditException('Gece kapanışını engelleyen kontroller: '.implode(', ', $blocking));
        }

        return array_map(fn ($c) => count($c[1]), $checks);
    }

    private function noShows(Property $property, string $bd, ?int $userId): array
    {
        $reservations = Reservation::query()->where('property_id', $property->id)->whereIn('status', ['confirmed', 'tentative'])->where('arrival_date', $bd)->get();
        foreach ($reservations as $reservation) {
            $reservation->update(['status' => 'no_show', 'no_show_at' => now(), 'room_id' => null]);
            $reservation->nightRows()->where('status', 'expected')->update(['status' => 'cancelled', 'room_id' => null]);
            DB::table('pms_reservation_status_logs')->insert(['reservation_id' => $reservation->id, 'from_status' => 'confirmed', 'to_status' => 'no_show',
                'business_date' => $bd, 'user_id' => $userId, 'created_at' => now()]);
            $this->availability->rebuild($property->id, $reservation->arrival_date, $reservation->departure_date, (int) $reservation->room_type_id);
        }

        return ['no_shows' => $reservations->count()];
    }

    private function roomCharges(Property $property, string $bd, ?int $userId): array
    {
        $count = 0;
        $total = 0.0;
        $nights = ReservationNight::query()->where('property_id', $property->id)->where('stay_date', $bd)->where('status', 'expected')
            ->whereHas('reservation', fn ($q) => $q->where('status', 'in_house'))->with('reservation')->get();

        foreach ($nights as $night) {
            $reservation = $night->reservation;
            if ($night->is_complimentary || $night->is_house_use) {
                $night->update(['status' => 'posted']);

                continue;
            }
            $folio = Folio::query()->where('reservation_id', $reservation->id)->where('window', 1)->where('status', 'open')->firstOrFail();
            $rateCode = RateCode::query()->with('package.elements', 'roomTransactionCode')->findOrFail($night->rate_code_id);
            $key = "audit:{$bd}:{$night->id}";
            $common = ['source' => 'night_audit', 'posted_by' => $userId, 'stay_date' => $bd, 'reservation_night_id' => $night->id, 'market_segment_id' => $night->market_segment_id];

            $packagePart = 0.0;
            if ($rateCode->package && $rateCode->package->is_included_in_rate) {
                $children = (int) $night->children;
                foreach ($rateCode->package->elements as $element) {
                    $amount = match ($element->calc) {
                        'per_room' => (float) $element->amount_adult,
                        'per_adult' => $night->adults * (float) $element->amount_adult,
                        'per_child' => $children * (float) $element->amount_child,
                        default => $night->adults * (float) $element->amount_adult + $children * (float) $element->amount_child,
                    };
                    if ($amount <= 0) {
                        continue;
                    }
                    $this->postings->charge($folio, TransactionCode::query()->findOrFail($element->transaction_code_id), $amount, 1, null,
                        array_merge($common, ['package_id' => $rateCode->package_id, 'idempotency_key' => $key.':pkg:'.$element->id]));
                    $packagePart += $amount;
                }
            }

            $roomAmount = round((float) $night->amount - $packagePart, 2);
            $posting = $this->postings->charge($folio, $rateCode->roomTransactionCode, $roomAmount, 1, $rateCode->roomTransactionCode->name.' '.CarbonImmutable::parse($bd)->format('d.m.Y'),
                array_merge($common, ['idempotency_key' => $key.':room']));
            $night->update(['status' => 'posted', 'posted_posting_id' => $posting->id]);
            $count++;
            $total += (float) $night->amount;
        }

        return ['room_charges_posted' => $count, 'amount_posted' => round($total, 2)];
    }

    private function statistics(Property $property, string $bd): array
    {
        DB::table('pms_daily_statistics')->where('property_id', $property->id)->where('business_date', $bd)->delete();
        DB::table('pms_daily_revenue')->where('property_id', $property->id)->where('business_date', $bd)->delete();
        $this->availability->rebuild($property->id, CarbonImmutable::parse($bd), CarbonImmutable::parse($bd));

        $revenue = DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.property_id', $property->id)->where('p.business_date', $bd)->where('t.type', 'revenue')
            ->groupBy('p.transaction_code_id', 'p.outlet_id')
            ->selectRaw('p.transaction_code_id, p.outlet_id, COUNT(*) AS c, SUM(p.net_amount) AS net, SUM(p.tax_amount) AS tax, SUM(p.amount) AS gross')->get();
        foreach ($revenue as $row) {
            DB::table('pms_daily_revenue')->insert(['property_id' => $property->id, 'business_date' => $bd, 'transaction_code_id' => $row->transaction_code_id,
                'outlet_id' => $row->outlet_id, 'postings_count' => $row->c, 'net_amount' => $row->net, 'tax_amount' => $row->tax, 'gross_amount' => $row->gross,
                'created_at' => now(), 'updated_at' => now()]);
        }

        $bucket = fn (array $buckets, string $col = 'net_amount') => (float) DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.property_id', $property->id)->where('p.business_date', $bd)->where('t.type', 'revenue')->whereIn('t.revenue_bucket', $buckets)->sum("p.{$col}");

        $rows = DB::table('pms_inventory_daily as i')->join('pms_room_types as rt', 'rt.id', '=', 'i.room_type_id')
            ->where('i.property_id', $property->id)->where('i.date', $bd)->select('i.*', 'rt.code', 'rt.beds')->get();
        $sold = fn (?int $typeId) => ReservationNight::query()->where('property_id', $property->id)->where('stay_date', $bd)->where('status', 'posted')
            ->when($typeId, fn ($q) => $q->where('room_type_id', $typeId))
            ->whereHas('reservation', fn ($q) => $q->whereIn('status', ['in_house', 'checked_out']));

        $insert = function (string $dimension, string $key, $inventory, ?int $typeId) use ($property, $bd, $sold, $bucket) {
            $available = (int) $inventory->sum('physical_rooms');
            $roomsSold = (clone $sold($typeId))->count();
            $roomRevenue = $typeId ? (float) (clone $sold($typeId))->sum('net_amount') : $bucket(['room']);
            $total = $typeId ? $roomRevenue : $bucket(['room', 'breakfast', 'lunch', 'dinner', 'allinc_food', 'allinc_beverage', 'food', 'beverage', 'minibar', 'telephone', 'spa', 'laundry', 'other']);
            DB::table('pms_daily_statistics')->insert([
                'property_id' => $property->id, 'business_date' => $bd, 'dimension' => $dimension, 'dimension_key' => $key,
                'rooms_available' => $available, 'rooms_ooo' => (int) $inventory->sum('out_of_order'), 'rooms_oos' => (int) $inventory->sum('out_of_service'),
                'rooms_sold' => $roomsSold, 'rooms_comp' => (clone $sold($typeId))->where('is_complimentary', true)->count(),
                'rooms_house_use' => (clone $sold($typeId))->where('is_house_use', true)->count(),
                'beds_available' => (int) $inventory->sum(fn ($i) => $i->physical_rooms * $i->beds),
                'adults' => (int) (clone $sold($typeId))->sum('adults'), 'children' => (int) (clone $sold($typeId))->sum('children'),
                'arrivals' => Reservation::query()->where('property_id', $property->id)->where('check_in_business_date', $bd)->when($typeId, fn ($q) => $q->where('room_type_id', $typeId))->count(),
                'departures' => Reservation::query()->where('property_id', $property->id)->where('check_out_business_date', $bd)->when($typeId, fn ($q) => $q->where('room_type_id', $typeId))->count(),
                'no_shows' => Reservation::query()->where('property_id', $property->id)->where('status', 'no_show')->where('arrival_date', $bd)->when($typeId, fn ($q) => $q->where('room_type_id', $typeId))->count(),
                'room_revenue' => $roomRevenue,
                'breakfast_revenue' => $typeId ? 0 : $bucket(['breakfast']), 'lunch_revenue' => $typeId ? 0 : $bucket(['lunch']), 'dinner_revenue' => $typeId ? 0 : $bucket(['dinner']),
                'fnb_revenue' => $typeId ? 0 : $bucket(['food', 'beverage']), 'minibar_revenue' => $typeId ? 0 : $bucket(['minibar']),
                'other_revenue' => $typeId ? 0 : $bucket(['spa', 'laundry', 'other']), 'total_revenue' => $total,
                'occupancy_pct' => $available > 0 ? round($roomsSold / $available * 100, 2) : 0,
                'adr' => $roomsSold > 0 ? round($roomRevenue / $roomsSold, 2) : 0,
                'revpar' => $available > 0 ? round($roomRevenue / $available, 2) : 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        $insert('total', '', $rows, null);
        foreach ($rows as $row) {
            $insert('room_type', $row->code, collect([$row]), (int) $row->room_type_id);
        }

        $guest = (float) DB::table('pms_postings')->where('property_id', $property->id)->where('business_date', $bd)->where('ledger', 'guest')->sum('amount');
        $opening = (float) (DB::table('pms_ledger_balances')->where('property_id', $property->id)->where('ledger', 'guest')->where('business_date', '<', $bd)->orderByDesc('business_date')->value('closing') ?? 0);
        DB::table('pms_ledger_balances')->updateOrInsert(['property_id' => $property->id, 'business_date' => $bd, 'ledger' => 'guest'], [
            'opening' => $opening,
            'debits' => (float) DB::table('pms_postings')->where('property_id', $property->id)->where('business_date', $bd)->where('ledger', 'guest')->where('amount', '>', 0)->sum('amount'),
            'credits' => -(float) DB::table('pms_postings')->where('property_id', $property->id)->where('business_date', $bd)->where('ledger', 'guest')->where('amount', '<', 0)->sum('amount'),
            'closing' => round($opening + $guest, 2), 'updated_at' => now(),
        ]);

        return ['room_types' => $rows->count()];
    }

    private function advance(Property $property, string $bd, int $runId, ?int $userId): array
    {
        $next = CarbonImmutable::parse($bd)->addDay()->toDateString();
        DB::table('pms_business_dates')->updateOrInsert(['property_id' => $property->id, 'business_date' => $bd],
            ['status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId, 'night_audit_run_id' => $runId, 'updated_at' => now()]);
        DB::table('pms_business_dates')->updateOrInsert(['property_id' => $property->id, 'business_date' => $next],
            ['status' => 'open', 'opened_at' => now(), 'updated_at' => now()]);
        $property->update(['current_business_date' => $next]);

        return ['new_business_date' => $next];
    }

    private function reportPack(int $propertyId, string $bd): array
    {
        return [
            'business_date' => $bd,
            'statistics' => DB::table('pms_daily_statistics')->where('property_id', $propertyId)->where('business_date', $bd)->get()->toArray(),
            'revenue' => DB::table('pms_daily_revenue as r')->join('pms_transaction_codes as t', 't.id', '=', 'r.transaction_code_id')
                ->where('r.property_id', $propertyId)->where('r.business_date', $bd)->select('t.code', 't.name', 'r.net_amount', 'r.tax_amount', 'r.gross_amount')->get()->toArray(),
            'ledger' => DB::table('pms_ledger_balances')->where('property_id', $propertyId)->where('business_date', $bd)->get()->toArray(),
        ];
    }
}
