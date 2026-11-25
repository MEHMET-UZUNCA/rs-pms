<?php

namespace App\Domain\Reservations\Exceptions;

use RuntimeException;

/** Rezervasyon kurallarına aykırı işlem (fiyat yok, oda dolu, bakiye açık …). */
class ReservationException extends RuntimeException {}
