<?php

declare(strict_types=1);

namespace KetaPay;

/**
 * Cancels a request and any retries still to come. Pass it as the `cancel` option, then call
 * `cancel()` from anything that runs while the request is in flight: a signal handler, a progress
 * callback, a deadline you check elsewhere.
 *
 *     $token = new CancellationToken();
 *     pcntl_signal(SIGTERM, fn () => $token->cancel());
 *     $ketapay->escrows->list([], ['cancel' => $token]);
 */
final class CancellationToken
{
    private bool $cancelled = false;

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
