<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** A webhook signature did not verify. Reject the request. */
class WebhookSignatureError extends KetaPayError {}
