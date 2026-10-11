<?php

declare(strict_types=1);

namespace KetaPay\Http;

/** No response came back. Thrown by transports; the SDK turns it into a ConnectionError or RequestAbortedError. */
final class TransportException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $timedOut = false,
        public readonly bool $cancelled = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
