<?php

namespace App\Domain\Housekeeping\Services;

use App\Domain\Core\Services\NumberSequenceService;
use App\Domain\Housekeeping\Models\LostFoundItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kayıp ve bulunan eşyalar (Fidelio Lost & Found). Kayıt → eşleştirme (kayıp ↔ bulunan) → teslim ya da imha.
 * Her adım hareket geçmişine yazılır; kapanmış eşya düzenlenmez (yalnız not eklenir).
 */
class LostFoundService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    /** @param array<string, mixed> $data */
    public function create(int $propertyId, string $kind, array $data, ?int $userId): LostFoundItem
    {
        return DB::transaction(function () use ($propertyId, $kind, $data, $userId) {
            $item = LostFoundItem::create($data + [
                'property_id' => $propertyId, 'kind' => $kind, 'status' => 'open', 'created_by' => $userId,
                'found_by' => $kind === 'found' ? $userId : null,
                'number' => ($kind === 'lost' ? 'L' : 'F').$this->numbers->next($propertyId, 'lost_found_'.$kind),
            ]);
            $this->log($item, $kind === 'lost' ? 'reported' : 'found', $data['note'] ?? null, $userId);

            return $item;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(LostFoundItem $item, array $data, ?int $userId): LostFoundItem
    {
        if ($item->isResolved()) {
            throw new RuntimeException(__('pms.lf.err_resolved'));
        }
        $item->update($data);
        $this->log($item, 'edited', null, $userId);

        return $item->fresh();
    }

    public function match(LostFoundItem $lost, LostFoundItem $found, ?int $userId): void
    {
        if ($lost->kind !== 'lost' || $found->kind !== 'found' || (int) $lost->property_id !== (int) $found->property_id) {
            throw new RuntimeException(__('pms.lf.err_match_kinds'));
        }
        if ($lost->status !== 'open' || $found->status !== 'open') {
            throw new RuntimeException(__('pms.lf.err_match_status'));
        }
        DB::transaction(function () use ($lost, $found, $userId) {
            $lost->update(['matched_id' => $found->id, 'status' => 'matched']);
            $found->update(['matched_id' => $lost->id, 'status' => 'matched']);
            $this->log($lost, 'matched', $found->number, $userId);
            $this->log($found, 'matched', $lost->number, $userId);
        });
    }

    public function unmatch(LostFoundItem $item, ?int $userId): void
    {
        if ($item->status !== 'matched' || ! $item->matched_id) {
            throw new RuntimeException(__('pms.lf.err_not_matched'));
        }
        DB::transaction(function () use ($item, $userId) {
            $other = LostFoundItem::find($item->matched_id);
            foreach (array_filter([$item, $other]) as $i) {
                $i->update(['matched_id' => null, 'status' => 'open']);
                $this->log($i, 'unmatched', null, $userId);
            }
        });
    }

    /** Teslim (returned: kime) ya da imha / bağış (disposed). Eşleşmişse karşı kayıt da kapanır. */
    public function resolve(LostFoundItem $item, string $how, ?string $to, ?string $note, ?int $userId): void
    {
        if ($item->isResolved()) {
            throw new RuntimeException(__('pms.lf.err_resolved'));
        }
        DB::transaction(function () use ($item, $how, $to, $note, $userId) {
            $values = ['status' => $how, 'resolved_at' => now()] + ($how === 'returned' ? ['returned_at' => now(), 'returned_to' => $to] : []);
            foreach (array_filter([$item, $item->matched_id ? LostFoundItem::find($item->matched_id) : null]) as $i) {
                $i->update($values);
                $this->log($i, $how, trim(($to ? $to.' — ' : '').($note ?? ''), ' —') ?: null, $userId);
            }
        });
    }

    public function note(LostFoundItem $item, string $note, ?int $userId): void
    {
        $this->log($item, 'note', $note, $userId);
    }

    public function delete(LostFoundItem $item): void
    {
        if ($item->matched_id || $item->isResolved()) {
            throw new RuntimeException(__('pms.lf.err_delete'));
        }
        $item->delete();
    }

    private function log(LostFoundItem $item, string $action, ?string $note, ?int $userId): void
    {
        DB::table('pms_lost_found_actions')->insert(['lost_found_id' => $item->id, 'action' => $action, 'note' => $note ? mb_substr($note, 0, 500) : null,
            'user_id' => $userId, 'created_at' => now()]);
    }
}
