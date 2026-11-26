<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Reservations\Models\Reservation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rezervasyon onay mektubu (Reservation Confirmation Letter).
 * Şablon: Confirmation_Letter_EN.pdf / Confirmation_Letter_TR.pdf — A4, tarayıcıdan yazdırılır / PDF'e kaydedilir.
 */
class ConfirmationLetterController extends Controller
{
    public const LANGUAGES = ['en', 'tr'];

    public function show(Request $request, Reservation $reservation): View
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
        $reservation->load(['profile', 'roomType', 'nightRows']);

        $lang = $request->query('dil');
        if (! in_array($lang, self::LANGUAGES, true)) {
            $lang = $reservation->profile?->language_code === 'tr' ? 'tr' : 'en';
        }

        return view('reservations.confirmation-letter', [
            'r' => $reservation,
            'lang' => $lang,
            'guest' => $this->guestName($reservation->profile),
            'rate' => $this->rate($reservation),
            'guests' => $this->guests($reservation, $lang),
            'bookedBy' => $this->bookedBy($reservation),
        ]);
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
