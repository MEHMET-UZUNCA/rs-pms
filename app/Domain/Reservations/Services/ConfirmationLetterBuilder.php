<?php

namespace App\Domain\Reservations\Services;

use App\Domain\Profiles\Models\Profile;
use App\Domain\Reservations\Models\Reservation;

/**
 * Onay mektubunun (Reservation Confirmation Letter) verisi — yazdırma sayfası ve e-posta aynı veriyi kullanır.
 * Şablon: Confirmation_Letter_EN.pdf / Confirmation_Letter_TR.pdf.
 */
class ConfirmationLetterBuilder
{
    public const LANGUAGES = ['en', 'tr'];

    /** Dil verilmezse misafir profilinin dili; Türkçe değilse İngilizce. */
    public function language(Reservation $reservation, ?string $requested): string
    {
        if (in_array($requested, self::LANGUAGES, true)) {
            return $requested;
        }

        return $reservation->profile?->language_code === 'tr' ? 'tr' : 'en';
    }

    /** @return array{r: Reservation, lang: string, guest: string, rate: string, guests: string, bookedBy: string} */
    public function build(Reservation $reservation, string $lang): array
    {
        $reservation->loadMissing(['profile', 'roomType', 'nightRows']);

        return [
            'r' => $reservation,
            'lang' => $lang,
            'guest' => $this->guestName($reservation->profile),
            'rate' => $this->rate($reservation),
            'guests' => $this->guests($reservation, $lang),
            'bookedBy' => $this->bookedBy($reservation),
        ];
    }

    /** Misafirin e-posta adresi (önce birincil). */
    public function guestEmail(Reservation $reservation): ?string
    {
        if (! $reservation->profile_id) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('pms_profile_communications')->where('profile_id', $reservation->profile_id)
            ->where('type', 'email')->orderByDesc('is_primary')->orderBy('id')->value('value');
    }

    private function guestName(?Profile $profile): string
    {
        if (! $profile) {
            return '';
        }
        $name = trim(implode(' ', array_filter([$profile->title, $profile->first_name, $profile->last_name])));

        return $profile->first_name || $profile->last_name ? $name : (string) $profile->display_name;
    }

    /** Gecelik fiyat: bütün geceler aynıysa tek tutar, değilse en düşük – en yüksek. */
    private function rate(Reservation $r): string
    {
        $amounts = $r->nightRows->pluck('amount')->map(fn ($a) => (float) $a)->unique()->sort()->values();
        if ($amounts->isEmpty()) {
            return '';
        }
        $fmt = fn (float $a) => number_format($a, 2, ',', '.');
        $text = $amounts->count() === 1 ? $fmt($amounts[0]) : $fmt($amounts->first()).' – '.$fmt($amounts->last());

        return $text.' '.$r->currency_code;
    }

    private function guests(Reservation $r, string $lang): string
    {
        $parts = [trans_choice('letter.adults', $r->adults, [], $lang)];
        if ($r->children > 0) {
            $parts[] = trans_choice('letter.children', $r->children, [], $lang);
        }
        if ($r->infants > 0) {
            $parts[] = trans_choice('letter.infants', $r->infants, [], $lang);
        }

        return implode(', ', $parts);
    }

    /** Acenta, yoksa şirket, yoksa rezervasyonu yapan kişi. */
    private function bookedBy(Reservation $r): string
    {
        foreach ([$r->agent_profile_id, $r->company_profile_id] as $id) {
            if ($id && ($name = Profile::whereKey($id)->value('display_name'))) {
                return $name;
            }
        }

        return (string) $r->booker_name;
    }
}
