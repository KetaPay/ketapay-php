<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** The Idempotency-Key was reused with a different request (409). */
class IdempotencyError extends KetaPayError {}
