<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** Builds the right error class from an API error response. Internal. */
final class ErrorFactory
{
    public static function fromResponse(int $status, mixed $body, ?string $requestId): KetaPayError
    {
        $error = is_object($body) && isset($body->error) && is_object($body->error) ? $body->error : new \stdClass();
        $type = self::knownType($error->type ?? null) ?? self::typeForStatus($status);
        $class = self::classFor($type, $status);

        return new $class(
            self::str($error, 'message') ?? "KetaPay API responded with HTTP $status",
            $type,
            self::str($error, 'code') ?? 'unknown_error',
            self::str($error, 'param'),
            $status,
            self::str($error, 'request_id') ?? $requestId,
        );
    }

    /** @return class-string<KetaPayError> */
    private static function classFor(string $type, int $status): string
    {
        return match ($type) {
            'invalid_request_error' => match ($status) {
                404 => NotFoundError::class,
                409 => ConflictError::class,
                default => InvalidRequestError::class,
            },
            'authentication_error' => AuthenticationError::class,
            'permission_error' => PermissionError::class,
            'idempotency_error' => IdempotencyError::class,
            'rate_limit_error' => RateLimitError::class,
            default => APIError::class,
        };
    }

    /** @return 'invalid_request_error'|'authentication_error'|'permission_error'|'idempotency_error'|'rate_limit_error'|'api_error'|null */
    private static function knownType(mixed $type): ?string
    {
        return match ($type) {
            'invalid_request_error', 'authentication_error', 'permission_error', 'idempotency_error', 'rate_limit_error', 'api_error' => $type,
            default => null,
        };
    }

    /** @return 'invalid_request_error'|'authentication_error'|'permission_error'|'rate_limit_error'|'api_error' */
    private static function typeForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'authentication_error',
            $status === 403 => 'permission_error',
            $status === 429 => 'rate_limit_error',
            $status >= 500 => 'api_error',
            default => 'invalid_request_error',
        };
    }

    private static function str(object $error, string $field): ?string
    {
        return isset($error->{$field}) && is_string($error->{$field}) ? $error->{$field} : null;
    }
}
