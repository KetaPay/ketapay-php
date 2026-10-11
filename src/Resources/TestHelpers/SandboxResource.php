<?php

declare(strict_types=1);

namespace KetaPay\Resources\TestHelpers;

use KetaPay\Errors\InvalidRequestError;
use KetaPay\Resources\AbstractResource;

/** @internal */
abstract class SandboxResource extends AbstractResource
{
    /** Refuses a live key before anything is sent. */
    protected function sandboxOnly(): void
    {
        if ($this->http->livemode) {
            throw new InvalidRequestError('Test helpers only work with sk_test_ keys', 'invalid_request_error', 'test_mode_only');
        }
    }
}
