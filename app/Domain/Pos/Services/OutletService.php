<?php

namespace App\Domain\Pos\Services;

use App\Domain\Pos\Models\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Outlet tanımları: kaydetme (departman kodlarıyla birlikte), silme, sıralama.
 *  - Bir departman kodu tek outlet'e bağlı olur; başka outlet'teki kod önce oradan çıkarılmalıdır.
 *  - Varsayılan kod outlet'in kodlarından biri olmalıdır; boş bırakılırsa en küçük numaralı gelir kodu seçilir.
 *  - Kayıtlarda, Simphony mesajlarında, günlük satışta, paketlerde ya da kasa terminalinde kullanılan outlet silinemez; pasife alınır.
 */
class OutletService
{
    /** Outlet'e bağlı kayıt tutan tablolar (silme kontrolü). */
    private const USED_IN = [
        'pms_postings' => 'folyo kayıtları', 'pms_pos_postings' => 'Simphony kayıtları', 'pms_pos_daily_sales' => 'günlük satış özetleri',
        'pms_daily_revenue' => 'günlük gelir', 'pms_package_elements' => 'paket elemanları', 'pms_cashier_terminals' => 'kasa terminalleri',
        'pms_pos_revenue_mappings' => 'Simphony gelir eşlemeleri', 'pms_pos_tender_mappings' => 'Simphony ödeme eşlemeleri',
    ];

    /**
     * @param  array<string, mixed>  $data  code, name, type, source_system, simphony_rvc_number, rsone_department_id, default_transaction_code_id, is_active
     * @param  array<int>  $codeIds  outlet'e bağlanacak departman kodları
     */
    public function save(int $propertyId, array $data, array $codeIds, ?Outlet $outlet = null): Outlet
    {
        return DB::transaction(function () use ($propertyId, $data, $codeIds, $outlet) {
            $codeIds = array_values(array_unique(array_map('intval', $codeIds)));
            $codes = DB::table('pms_transaction_codes as tc')->leftJoin('pms_outlets as o', 'o.id', '=', 'tc.outlet_id')
                ->where('tc.property_id', $propertyId)->whereIn('tc.id', $codeIds)
                ->get(['tc.id', 'tc.code', 'tc.type', 'tc.outlet_id', 'o.name as outlet_name']);

            if ($codes->count() !== count($codeIds)) {
                throw new OutletException('Seçilen departman kodlarından biri bu tesise ait değil.');
            }
            if ($payment = $codes->firstWhere('type', 'payment')) {
                throw new OutletException("{$payment->code} bir ödeme kodu; outlet'e yalnız gelir kodları bağlanır.");
            }
            foreach ($codes as $code) {
                if ($code->outlet_id && (int) $code->outlet_id !== $outlet?->id) {
                    throw new OutletException("{$code->code} kodu \"{$code->outlet_name}\" outlet'ine bağlı. Önce oradan çıkarın.");
                }
            }

            $default = $data['default_transaction_code_id'] ?? null;
            if ($default && ! in_array((int) $default, $codeIds, true)) {
                throw new OutletException('Varsayılan kod, outlet\'in kodlarından biri olmalı.');
            }
            $data['default_transaction_code_id'] = $default
                ? (int) $default
                : $codes->sortBy(fn ($c) => (int) $c->code)->first()?->id;

            $outlet ??= new Outlet([
                'property_id' => $propertyId,
                'sort' => (int) DB::table('pms_outlets')->where('property_id', $propertyId)->max('sort') + 1,
            ]);
            $outlet->fill($data)->save();

            DB::table('pms_transaction_codes')->where('outlet_id', $outlet->id)->whereNotIn('id', $codeIds)->update(['outlet_id' => null, 'updated_at' => now()]);
            DB::table('pms_transaction_codes')->whereIn('id', $codeIds)->update(['outlet_id' => $outlet->id, 'updated_at' => now()]);

            return $outlet->refresh();
        });
    }

    public function delete(Outlet $outlet): void
    {
        $usage = [];
        foreach (self::USED_IN as $table => $label) {
            if (DB::table($table)->where('outlet_id', $outlet->id)->exists()) {
                $usage[] = $label;
            }
        }
        if ($usage !== []) {
            throw new OutletException("\"{$outlet->name}\" kullanımda (".implode(', ', $usage).'); silinemez, pasife alın.');
        }

        DB::transaction(function () use ($outlet) {
            DB::table('pms_transaction_codes')->where('outlet_id', $outlet->id)->update(['outlet_id' => null, 'updated_at' => now()]);
            $outlet->delete();
        });
    }

    /** Fidelio'daki Up / Down: komşu outlet'le sırasını değiştirir. */
    public function move(Outlet $outlet, string $direction): void
    {
        DB::transaction(function () use ($outlet, $direction) {
            $ordered = Outlet::where('property_id', $outlet->property_id)->orderBy('sort')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $ordered->search(fn ($o) => $o->id === $outlet->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= $ordered->count()) {
                return;
            }
            $list = $ordered->all();
            [$list[$index], $list[$swap]] = [$list[$swap], $list[$index]];
            foreach ($list as $i => $o) {
                if ($o->sort !== $i + 1) {
                    $o->update(['sort' => $i + 1]);
                }
            }
        });
    }
}
