<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** Something failed on KetaPay's side (5xx). Retried automatically up to `maxRetries`. */
class APIError extends KetaPayError {}
