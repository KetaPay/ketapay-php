<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\Errors\APIError;
use KetaPay\Errors\AuthenticationError;
use KetaPay\Errors\ConflictError;
use KetaPay\Errors\ConnectionError;
use KetaPay\Errors\IdempotencyError;
use KetaPay\Errors\InvalidRequestError;
use KetaPay\Errors\KetaPayError;
use KetaPay\Errors\NotFoundError;
use KetaPay\Errors\PermissionError;
use KetaPay\Errors\RateLimitError;
use KetaPay\Http\TransportException;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

final class ErrorsRetriesTest extends TestCase
{
    /** @return iterable<string, array{int, string, string, class-string<KetaPayError>}> */
    public static function errors(): iterable
    {
        yield '400' => [400, 'invalid_request_error', 'parameter_invalid', InvalidRequestError::class];
        yield '401' => [401, 'authentication_error', 'api_key_invalid', AuthenticationError::class];
        yield '403' => [403, 'permission_error', 'customer_not_allowed', PermissionError::class];
        yield '404' => [404, 'invalid_request_error', 'resource_not_found', NotFoundError::class];
        yield '409 conflict' => [409, 'invalid_request_error', 'dispute_open', ConflictError::class];
        yield '409 idempotency' => [409, 'idempotency_error', 'idempotency_key_reused', IdempotencyError::class];
        yield '429' => [429, 'rate_limit_error', 'rate_limited', RateLimitError::class];
        yield '500' => [500, 'api_error', 'internal_error', APIError::class];
    }

    /** @param class-string<KetaPayError> $class */
    #[DataProvider('errors')]
    #[TestDox('[ERR-001] maps HTTP $status $type to its error class')]
    public function testMapsErrors(int $status, string $type, string $code, string $class): void
    {
        $t = new MockTransport(self::apiError($status, $type, $code));
        $e = self::catching($class, fn() => self::client($t, ['maxRetries' => 0])->escrows->retrieve('e1'));
        self::assertSame($class, $e::class);
        self::assertSame($code, $e->code);
        self::assertSame($type, $e->type);
        self::assertSame('req_abc', $e->requestId);
        self::assertSame($status, $e->statusCode);
    }

    #[TestDox('[ERR-001] falls back on the status when the body has no known error type')]
    public function testFallsBackOnStatus(): void
    {
        $t = new MockTransport(['status' => 403, 'raw' => '<html>forbidden</html>', 'headers' => ['KetaPay-Request-Id' => 'req_h']]);
        $e = self::catching(PermissionError::class, fn() => self::client($t)->escrows->retrieve('e1'));
        self::assertSame('unknown_error', $e->code);
        self::assertSame('req_h', $e->requestId);
    }

    #[TestDox('[ERR-002] keeps NotFoundError and ConflictError catchable as InvalidRequestError')]
    public function testSubclasses(): void
    {
        $t = new MockTransport(self::apiError(404, 'invalid_request_error', 'resource_not_found'), self::apiError(409, 'invalid_request_error', 'dispute_open'));
        $k = self::client($t);
        self::catching(InvalidRequestError::class, fn() => $k->escrows->retrieve('e1'));
        self::catching(InvalidRequestError::class, fn() => $k->escrows->retrieve('e1'));
    }

    #[TestDox('[ERR-003] keeps the code, param, status and request id from the response')]
    public function testErrorFields(): void
    {
        $t = new MockTransport(self::apiError(400, 'invalid_request_error', 'parameter_invalid', 'buyer.phone'));
        $e = self::catching(InvalidRequestError::class, fn() => self::client($t)->escrows->retrieve('e1'));
        self::assertSame('buyer.phone', $e->param);
        self::assertSame('parameter_invalid', $e->getCode());
        self::assertSame('parameter_invalid happened', $e->getMessage());
    }

    #[TestDox('[RES-001] [RES-002] retries 5xx with the same idempotency key, then succeeds')]
    public function testRetries5xx(): void
    {
        $t = new MockTransport(self::apiError(500, 'api_error', 'internal_error'), ['body' => ['id' => 'cs_1']]);
        self::assertSame('cs_1', self::client($t)->checkoutSessions->cancel('cs_1')->id);
        self::assertCount(2, $t->requests);
        self::assertSame($t->requests[0]->headers['Idempotency-Key'], $t->requests[1]->headers['Idempotency-Key']);
    }

    #[TestDox('[RES-001] [RES-007] retries 429 and network errors, and warns about each retry')]
    public function testRetries429AndNetwork(): void
    {
        $warnings = [];
        $logger = static function (string $level) use (&$warnings): void {
            $warnings[] = $level;
        };
        $t = new MockTransport(self::apiError(429, 'rate_limit_error', 'rate_limited'), new TransportException('connection refused'), ['body' => ['ok' => true]]);
        self::assertTrue(self::client($t, ['logLevel' => 'warn', 'logger' => $logger])->escrows->retrieve('e1')['ok']);
        self::assertCount(3, $t->requests);
        self::assertSame(['warning', 'warning'], $warnings);
    }

    #[TestDox('[RES-003] does not retry other 4xx')]
    public function testNo4xxRetry(): void
    {
        $t = new MockTransport(self::apiError(409, 'invalid_request_error', 'dispute_open'), ['body' => []]);
        self::catching(ConflictError::class, fn() => self::client($t)->escrows->retrieve('e1'));
        self::assertCount(1, $t->requests);
    }

    #[TestDox('[RES-001] gives up after maxRetries, per client or per call')]
    public function testGivesUp(): void
    {
        $t = new MockTransport(self::apiError(503, 'api_error', 'internal_error'), self::apiError(503, 'api_error', 'internal_error'));
        self::catching(APIError::class, fn() => self::client($t, ['maxRetries' => 1])->escrows->retrieve('e1'));
        self::assertCount(2, $t->requests);

        $t = new MockTransport(self::apiError(503, 'api_error', 'internal_error'));
        self::catching(APIError::class, fn() => self::client($t)->escrows->retrieve('e1', ['maxRetries' => 0]));
        self::assertCount(1, $t->requests);
    }

    #[TestDox('[RES-001] throws RateLimitError and ConnectionError when retries run out')]
    public function testRetriesRunOut(): void
    {
        $limited = new MockTransport(self::apiError(429, 'rate_limit_error', 'rate_limited'));
        self::catching(RateLimitError::class, fn() => self::client($limited, ['maxRetries' => 0])->escrows->retrieve('e1'));
        $offline = new MockTransport(new TransportException('connection refused'));
        $e = self::catching(ConnectionError::class, fn() => self::client($offline, ['maxRetries' => 0])->escrows->retrieve('e1'));
        self::assertSame('network_error', $e->code);
        self::assertSame('connection_error', $e->type);
    }
}
