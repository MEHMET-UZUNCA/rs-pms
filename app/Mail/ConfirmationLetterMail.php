<?php

namespace App\Mail;

use App\Domain\Reservations\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Onay mektubu e-postası: şablonla aynı metinler, görseller e-postaya gömülü. */
class ConfirmationLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{r: Reservation, lang: string, guest: string, rate: string, guests: string, bookedBy: string} $letter */
    public function __construct(public array $letter) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('letter.subject', ['number' => $this->letter['r']->confirmation_number], $this->letter['lang']));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmation-letter', with: $this->letter);
    }
}
