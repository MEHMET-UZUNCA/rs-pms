<?php

namespace App\Domain\Pos\Simphony;

use InvalidArgumentException;

/**
 * IFC_CHG_PST isteği — docs/simphony-protokol.md §4–5. Üç biçim:
 *  - misafir arama:   <giriş> FS <ödeme tipi>
 *  - seçili misafir:  <giriş> FS <seçilen satır> FS S<n> FS <ödeme tipi> FS <tutar> FS <gövde>
 *  - oda dışı ödeme:  " " FS " " FS <ödeme tipi> FS <tutar> FS <gövde>
 * Gövde: 16 FS satış… FS <n> FS indirim… FS <n> FS servis… FS <n> FS vergi… FS çek FS garson FS dönem FS RVC FS kişi FS tarih FS saat FS önceki ödeme
 */
final class ChargeRequest
{
    public string $kind;               // lookup / selected / non_front_office

    public string $guestInput = '';

    public string $selectionText = '';

    public int $selection = 0;

    public int $tender = 0;

    public float $amount = 0.0;

    /** @var array<int,float> 1 tabanlı */
    public array $sales = [];

    /** @var array<int,float> */
    public array $discounts = [];

    /** @var array<int,float> */
    public array $services = [];

    /** @var array<int,float> */
    public array $taxes = [];

    public string $check = '';

    public string $employee = '';

    public string $period = '';

    public int $rvc = 0;

    public int $guests = 0;

    public string $date = '';

    public string $time = '';

    public float $previousPayment = 0.0;

    public static function fromFrame(Frame $frame): self
    {
        $f = $frame->fields;
        $r = new self;
        if (count($f) === 2) {
            $r->kind = 'lookup';
            $r->guestInput = trim($f[0]);
            $r->tender = (int) $f[1];

            return $r;
        }
        if (trim($f[0] ?? '') === '' && trim($f[1] ?? '') === '') {
            $r->kind = 'non_front_office';
            $i = 2;
        } else {
            $r->kind = 'selected';
            $r->guestInput = trim($f[0]);
            $r->selectionText = rtrim($f[1] ?? '');
            $r->selection = (int) ltrim($f[2] ?? '', 'Ss');
            $i = 3;
        }
        $r->tender = (int) ($f[$i] ?? 0);
        $r->amount = self::num($f[$i + 1] ?? '0');
        $i += 2;
        foreach (['sales', 'discounts', 'services', 'taxes'] as $list) {
            $count = (int) ($f[$i] ?? -1);
            if ($count < 0 || $i + $count >= count($f)) {
                throw new InvalidArgumentException("IFC_CHG_PST: {$list} bölümü çözülemedi.");
            }
            $values = [];
            for ($k = 1; $k <= $count; $k++) {
                $values[$k] = self::num($f[$i + $k]);
            }
            $r->{$list} = $values;
            $i += $count + 1;
        }
        [$r->check, $r->employee, $r->period] = [trim($f[$i] ?? ''), trim($f[$i + 1] ?? ''), trim($f[$i + 2] ?? '')];
        $r->rvc = (int) ($f[$i + 3] ?? 0);
        $r->guests = (int) ($f[$i + 4] ?? 0);
        $r->date = trim($f[$i + 5] ?? '');
        $r->time = trim($f[$i + 6] ?? '');
        $r->previousPayment = self::num($f[$i + 7] ?? '0');

        return $r;
    }

    /** Simphony ondalık ayırıcı nokta ya da virgül olabilir; eksi işaret başta. */
    public static function num(string $value): float
    {
        return (float) str_replace(',', '.', trim($value));
    }

    /** Aynı ödeme ikinci kez gelirse (Simphony tekrar) tek kayıt olsun diye. */
    public function idempotencyKey(): string
    {
        return 'simphony:'.implode(':', [$this->rvc, $this->check, $this->date, $this->time, $this->tender, number_format($this->amount, 2, '.', ''),
            number_format($this->previousPayment, 2, '.', ''), $this->selection]);
    }
}
