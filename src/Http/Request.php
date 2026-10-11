<?php

declare(strict_types=1);

namespace KetaPay\Http;

use KetaPay\CancellationToken;

/** One HTTP attempt, as handed to a transport. */
final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body,
        public readonly int $timeoutMs,
        public readonly ?CancellationToken $cancel = null,
    ) {}
}
