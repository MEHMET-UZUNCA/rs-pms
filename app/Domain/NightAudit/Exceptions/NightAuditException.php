<?php

namespace App\Domain\NightAudit\Exceptions;

use RuntimeException;

/** Gece kapanışı başlatılamadı ya da engelleyen kontrol var. */
class NightAuditException extends RuntimeException {}
