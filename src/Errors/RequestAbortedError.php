<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** You cancelled the request with a `CancellationToken`. */
class RequestAbortedError extends KetaPayError {}
