<?php

namespace App\Domain\Cashiering\Services;

use App\Domain\Cashiering\Exceptions\PostingException;
use App\Domain\Core\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kasiyer ve vardiya (Fidelio Cashier / Close Cashier).
 *  - Her kullanıcının bir kasiyer kaydı vardır (ilk kayıtta açılır; numara sıradaki).
 *  - Kullanıcı folyoya ilk elle kayıt ya da ödeme attığında vardiyası kendiliğinden açılır; kayıtlar vardiyaya bağlanır.
 *  - Kasa kapanışı: vardiyadaki ödemeler ödeme kodu ve para birimine göre toplanır (sistem tutarı), sayılan tutar girilir, fark yazılır,
 *    vardiya kapanır; sonraki kayıtta yeni vardiya açılır.
 */
class CashierService
{
    public function cashier(int $propertyId, User $user): object
    {
        $cashier = DB::table('pms_cashiers')->where('property_id', $propertyId)->where('user_id', $user->id)->first();
        if ($cashier) {
            return $cashier;
        }

        return DB::transaction(function () use ($propertyId, $user) {
            $number = (int) DB::table('pms_cashiers')->where('property_id', $propertyId)->lockForUpdate()->max('number') + 1;
            $id = DB::table('pms_cashiers')->insertGetId([
                'property_id' => $propertyId, 'number' => $number, 'initials' => mb_substr((string) ($user->initials ?? $user->username), 0, 3),
                'name' => $user->name ?: $user->username, 'user_id' => $user->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('pms_cashiers')->where('id', $id)->first();
        });
    }

    /** Kullanıcının açık vardiyası (yoksa null). */
    public function openShift(int $propertyId, User $user): ?object
    {
        $cashier = DB::table('pms_cashiers')->where('property_id', $propertyId)->where('user_id', $user->id)->first();

        return $cashier ? DB::table('pms_cashier_shifts')->where('cashier_id', $cashier->id)->where('status', 'open')->orderByDesc('id')->first() : null;
    }

    /** Açık vardiya; yoksa açılır (iş gününe). */
    public function shiftFor(int $propertyId, User $user): object
    {
        if ($shift = $this->openShift($propertyId, $user)) {
            return $shift;
        }
        $cashier = $this->cashier($propertyId, $user);
        $id = DB::table('pms_cashier_shifts')->insertGetId([
            'property_id' => $propertyId, 'cashier_id' => $cashier->id, 'status' => 'open',
            'business_date' => Property::query()->findOrFail($propertyId)->current_business_date->toDateString(),
            'opened_at' => now(), 'opened_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('pms_cashier_shifts')->where('id', $id)->first();
    }

    /**
     * Vardiyanın ödeme toplamları: ödeme kodu × para birimi. Sistem tutarı kasaya giren (ödeme pozitif, iade negatif);
     * dövizde döviz tutarı, TL karşılığı ayrıca.
     *
     * @return Collection<int, object{transaction_code_id: int, code: string, name: string, payment_kind: ?string, currency_code: string, system_amount: float, base_amount: float, count: int}>
     */
    public function totals(object $shift): Collection
    {
        $base = Property::query()->findOrFail($shift->property_id)->base_currency;

        return DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.cashier_shift_id', $shift->id)->whereIn('t.type', ['payment', 'deposit', 'paid_out'])
            ->groupBy('p.transaction_code_id', 't.code', 't.name', 't.payment_kind', 'p.foreign_currency')
            ->orderByRaw('CAST(t.code AS UNSIGNED)')
            ->selectRaw('p.transaction_code_id, t.code, t.name, t.payment_kind, p.foreign_currency,
                SUM(-p.amount) AS base_amount, SUM(-COALESCE(p.foreign_amount, p.amount)) AS system_amount, COUNT(*) AS cnt')
            ->get()
            ->map(fn ($r) => (object) ['transaction_code_id' => (int) $r->transaction_code_id, 'code' => $r->code, 'name' => $r->name, 'payment_kind' => $r->payment_kind,
                'currency_code' => $r->foreign_currency ?: $base, 'system_amount' => round((float) $r->system_amount, 2), 'base_amount' => round((float) $r->base_amount, 2), 'count' => (int) $r->cnt]);
    }

    /** Vardiyadaki harcama kayıtları toplamı (departman koduna göre). */
    public function charges(object $shift): Collection
    {
        return DB::table('pms_postings as p')->join('pms_transaction_codes as t', 't.id', '=', 'p.transaction_code_id')
            ->where('p.cashier_shift_id', $shift->id)->whereNotIn('t.type', ['payment', 'deposit', 'paid_out'])
            ->groupBy('t.code', 't.name')->orderByRaw('CAST(t.code AS UNSIGNED)')
            ->selectRaw('t.code, t.name, SUM(p.amount) AS amount, COUNT(*) AS cnt')->get();
    }

    /**
     * Kasa kapanışı. $counted: "kodId|döviz" => sayılan tutar (boşsa sistem tutarı kabul edilir).
     *
     * @param  array<string, float|string|null>  $counted
     */
    public function close(object $shift, array $counted, ?string $notes, int $userId): void
    {
        if ($shift->status !== 'open') {
            throw new PostingException(__('pms.err.shift_closed'));
        }
        DB::transaction(function () use ($shift, $counted, $notes, $userId) {
            foreach ($this->totals($shift) as $t) {
                $raw = $counted[$t->transaction_code_id.'|'.$t->currency_code] ?? null;
                $value = $raw === null || $raw === '' ? $t->system_amount : round((float) str_replace(',', '.', (string) $raw), 2);
                DB::table('pms_cashier_shift_totals')->updateOrInsert(
                    ['shift_id' => $shift->id, 'transaction_code_id' => $t->transaction_code_id, 'currency_code' => $t->currency_code],
                    ['system_amount' => $t->system_amount, 'counted_amount' => $value, 'difference' => round($value - $t->system_amount, 2)]);
            }
            DB::table('pms_cashier_shifts')->where('id', $shift->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId,
                'notes' => $notes, 'updated_at' => now()]);
        });
    }
}
