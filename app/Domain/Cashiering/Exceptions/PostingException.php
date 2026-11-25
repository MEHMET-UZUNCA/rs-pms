<?php

namespace App\Domain\Cashiering\Exceptions;

use RuntimeException;

/** Folyo kaydı kurallarına aykırı işlem (kapalı folyo, kapalı iş günü, ikinci ters kayıt …). */
class PostingException extends RuntimeException {}
