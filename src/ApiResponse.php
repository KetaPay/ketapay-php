<?php

declare(strict_types=1);

namespace KetaPay;

/** The raw response behind an object: status, headers and `KetaPay-Request-Id`. */
final class ApiResponse
{
    /**
     * @param array<string, string> $headers lower-case names
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body,
        /** `KetaPay-Request-Id`: quote it to KetaPay support. */
        public readonly ?string $requestId,
    ) {}

    /** A response header, by any casing: `header('X-RateLimit-Remaining')`. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
