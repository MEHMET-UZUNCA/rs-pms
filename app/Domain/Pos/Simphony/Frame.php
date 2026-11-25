<?php

namespace App\Domain\Pos\Simphony;

use InvalidArgumentException;

/**
 * Simphony MF_ENH (Enhanced PMS) mesaj çerçevesi — docs/simphony-protokol.md §1.
 *   SOH <kasa no 9><arayüz adı 16> STX FS <sıra 2><R|boşluk><mesaj adı> FS <alan> … ETX <checksum 4 hex> EOT
 */
final class Frame
{
    public const SOH = "\x01";

    public const STX = "\x02";

    public const ETX = "\x03";

    public const EOT = "\x04";

    public const FS = "\x1c";

    /** @param list<string> $fields */
    public function __construct(
        public readonly string $source,        // "000000006MF_ENH          " (kasa no + arayüz adı, olduğu gibi geri gönderilir)
        public readonly string $sequence,      // "01" … "99"
        public readonly bool $retransmit,
        public readonly string $type,          // IFC_CHG_PST, FO_FI_RES_QUERY …
        public readonly array $fields,
    ) {}

    public static function decode(string $raw): self
    {
        $soh = strpos($raw, self::SOH);
        $stx = strpos($raw, self::STX);
        $etx = strrpos($raw, self::ETX);
        if ($soh === false || $stx === false || $etx === false || $stx < $soh || $etx < $stx) {
            throw new InvalidArgumentException('Geçersiz Simphony çerçevesi.');
        }
        $source = substr($raw, $soh + 1, $stx - $soh - 1);
        $data = substr($raw, $stx + 1, $etx - $stx - 1);
        if (! str_starts_with($data, self::FS)) {
            throw new InvalidArgumentException('Çerçevede FS ile başlayan veri yok.');
        }
        $parts = explode(self::FS, substr($data, 1));
        $head = array_shift($parts);
        if (! preg_match('/^([ \d]{2})([ R])\s*([A-Z0-9_]+)$/', $head, $m)) {
            throw new InvalidArgumentException('Mesaj başlığı çözülemedi: '.$head);
        }

        return new self($source, $m[1], $m[2] === 'R', $m[3], $parts);
    }

    /** Cevap: aynı kaynak ve sıra numarasıyla. */
    public function reply(string $type, array $fields = []): self
    {
        return new self($this->source, $this->sequence, false, $type, array_values(array_map('strval', $fields)));
    }

    public function encode(): string
    {
        $body = $this->source.self::STX.self::FS.$this->sequence.' '.$this->type;
        foreach ($this->fields as $field) {
            $body .= self::FS.$field;
        }
        $body .= self::ETX;

        return self::SOH.$body.self::checksum($body).self::EOT;
    }

    /** SOH'tan sonraki ilk karakterden ETX dahil bütün baytların 16 bitlik toplamı, 4 büyük harf hex. */
    public static function checksum(string $bytes): string
    {
        $sum = 0;
        foreach (unpack('C*', $bytes) as $byte) {
            $sum = ($sum + $byte) & 0xFFFF;
        }

        return sprintf('%04X', $sum);
    }

    public function workstation(): string
    {
        return ltrim(substr($this->source, 0, strlen($this->source) - 16), '0 ') ?: '0';
    }

    public function field(int $index, string $default = ''): string
    {
        return $this->fields[$index] ?? $default;
    }
}
