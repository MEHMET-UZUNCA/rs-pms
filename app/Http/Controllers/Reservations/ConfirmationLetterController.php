<?php

namespace App\Http\Controllers\Reservations;

use App\Domain\Core\Models\Property;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Services\ConfirmationLetterBuilder;
use App\Http\Controllers\Controller;
use App\Mail\ConfirmationLetterMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Rezervasyon onay mektubu (Reservation Confirmation Letter).
 * Şablon: Confirmation_Letter_EN.pdf / Confirmation_Letter_TR.pdf — A4, tarayıcıdan yazdırılır / PDF'e kaydedilir ya da e-postayla gönderilir.
 */
class ConfirmationLetterController extends Controller
{
    public const LANGUAGES = ConfirmationLetterBuilder::LANGUAGES;

    public function __construct(private readonly ConfirmationLetterBuilder $letters) {}

    public function show(Request $request, Reservation $reservation): View
    {
        $this->authorizeProperty($reservation);
        $lang = $this->letters->language($reservation, $request->query('dil'));

        return view('reservations.confirmation-letter', $this->letters->build($reservation, $lang) + [
            'email' => $this->letters->guestEmail($reservation),
            'sent' => DB::table('pms_reservation_letters as l')->leftJoin('users as u', 'u.id', '=', 'l.sent_by')
                ->where('l.reservation_id', $reservation->id)->where('l.type', 'confirmation')->orderByDesc('l.id')
                ->first(['l.email', 'l.language', 'l.sent_at', 'u.username']),
            'mailReady' => self::mailConfigured(),
        ]);
    }

    public function send(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeProperty($reservation);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:150'], 'dil' => ['required', 'in:'.implode(',', self::LANGUAGES)]]);
        $back = redirect()->route('reservations.letter', [$reservation, 'dil' => $data['dil']]);

        if (! self::mailConfigured()) {
            return $back->with('error', __('pms.res.letter_no_smtp'));
        }

        try {
            Mail::to($data['email'])->send(new ConfirmationLetterMail($this->letters->build($reservation, $data['dil'])));
        } catch (Throwable $e) {
            Log::warning('Onay mektubu gönderilemedi', ['reservation' => $reservation->id, 'error' => $e->getMessage()]);

            return $back->with('error', __('pms.res.letter_failed'));
        }

        DB::table('pms_reservation_letters')->insert([
            'reservation_id' => $reservation->id, 'type' => 'confirmation', 'language' => $data['dil'], 'email' => $data['email'],
            'sent_by' => $request->user()->id, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $back->with('status', __('pms.res.letter_sent', ['email' => $data['email']]));
    }

    /** SMTP seçilmiş ama sunucu ya da gönderen adresi girilmemişse e-posta gönderilmez (.env: MAIL_HOST, MAIL_FROM_ADDRESS). */
    public static function mailConfigured(): bool
    {
        if ((string) config('mail.default') !== 'smtp') {
            return true;
        }

        return filled(config('mail.mailers.smtp.host')) && filled(config('mail.from.address'));
    }

    private function authorizeProperty(Reservation $reservation): void
    {
        abort_unless((int) $reservation->property_id === (int) app(Property::class)->id, 404);
    }
}
