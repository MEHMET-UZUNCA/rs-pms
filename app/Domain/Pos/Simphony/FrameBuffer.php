<?php

namespace App\Domain\Pos\Simphony;

/**
 * TCP akışından tam çerçeveleri ayırır: SOH … EOT. Parça parça gelen veriyi biriktirir; SOH öncesindeki çöp atılır.
 * Ping çerçevesi (SOH <kaynak> STX ETX EOT) da tam çerçeve olarak döner.
 */
final class FrameBuffer
{
    private string $buffer = '';

    /** @return list<string> */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $frames = [];
        while (true) {
            $start = strpos($this->buffer, Frame::SOH);
            if ($start === false) {
                $this->buffer = '';

                break;
            }
            $end = strpos($this->buffer, Frame::EOT, $start);
            if ($end === false) {
                $this->buffer = substr($this->buffer, $start);

                break;
            }
            $frames[] = substr($this->buffer, $start, $end - $start + 1);
            $this->buffer = substr($this->buffer, $end + 1);
        }

        return $frames;
    }

    public static function isPing(string $raw): bool
    {
        return (bool) preg_match('/^\x01[^\x02]*\x02\x03[0-9A-F]{0,4}\x04$/', $raw);
    }
}
