<?php

declare(strict_types=1);

namespace KetaPay\Tests\Support;

use KetaPay\KetaPay;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    protected static function client(MockTransport $transport, array $config = []): KetaPay
    {
        /** @phpstan-ignore argument.type (tests pass partial configs on purpose) */
        return new KetaPay(['apiKey' => 'sk_test_123', 'transport' => $transport, 'maxRetries' => 2, 'logLevel' => 'off', ...$config]);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    protected static function apiError(int $status, string $type, string $code, ?string $param = null): array
    {
        return [
            'status' => $status,
            'body' => ['error' => ['type' => $type, 'code' => $code, 'message' => "$code happened", 'param' => $param, 'request_id' => 'req_abc']],
        ];
    }

    /**
     * @param list<array<string, mixed>> $data
     *
     * @return array{body: array<string, mixed>}
     */
    protected static function page(array $data, ?string $next): array
    {
        return ['body' => ['object' => 'list', 'data' => $data, 'has_more' => $next !== null, 'next_cursor' => $next]];
    }

    /**
     * Runs $fn and returns what it threw, failing the test if it threw nothing.
     *
     * @template E of \Throwable
     *
     * @param class-string<E> $class
     *
     * @return E
     */
    protected static function catching(string $class, callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e, 'threw ' . $e::class . ': ' . $e->getMessage());

            return $e;
        }
        self::fail("Expected $class, nothing was thrown");
    }
}
