<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** Too many requests for this key (429). Retried automatically up to `maxRetries`. */
class RateLimitError extends KetaPayError {}
