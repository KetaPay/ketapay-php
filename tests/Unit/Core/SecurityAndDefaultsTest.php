<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\Errors\APIError;
use KetaPay\KetaPay;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class SecurityAndDefaultsTest extends TestCase
{
    #[TestDox('[SEC-002] refuses a plain-http base URL except localhost')]
    public function testHttpsOnly(): void
    {
        foreach (['http://api.ketapay.com/v1', 'not a url', 'ftp://localhost', 'http://localhost.evil.com'] as $bad) {
            $e = self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['apiKey' => 'sk_test_x', 'baseUrl' => $bad]));
            self::assertStringContainsString('https', $e->getMessage(), $bad);
        }
        foreach (['http://localhost:4010', 'http://127.0.0.1:4010', 'https://staging.example.com/v1'] as $good) {
            self::assertFalse((new KetaPay(['apiKey' => 'sk_test_x', 'baseUrl' => $good]))->livemode);
        }
    }

    #[TestDox('[SEC-006] money-moving calls refuse to run without a customer token, before sending anything')]
    public function testCustomerTokenRequired(): void
    {
        $t = new MockTransport();
        $k = self::client($t);
        // PHPStan refuses every one of these calls (the static half of SEC-006): each ignore below fails the
        // lint if the signature ever stops requiring the token. The runtime half is asserted underneath.
        $calls = [
            'see a release code' => fn() => $k->escrows->milestones->releaseCode('e1', 'm1', []), // @phpstan-ignore argument.type
            'open a dispute' => fn() => $k->disputes->create('e1', ['reason' => 'damaged', 'description' => 'Arrived cracked'], ['idempotencyKey' => 'x']), // @phpstan-ignore argument.type
            'concede' => fn() => $k->disputes->concede('d1', []), // @phpstan-ignore argument.type
            'escalate' => fn() => $k->disputes->escalate('d1', []), // @phpstan-ignore argument.type
            'withdraw' => fn() => $k->disputes->withdraw('d1', []), // @phpstan-ignore argument.type
            'add evidence' => fn() => $k->disputes->submitEvidence('d1', ['statement' => 'Photos attached'], []), // @phpstan-ignore argument.type
            'list evidence' => fn() => $k->disputes->listEvidence('d1', []), // @phpstan-ignore argument.type
            'add chargeback evidence' => fn() => $k->chargebacks->submitEvidence('c1', ['statement' => 'Courier receipt'], []), // @phpstan-ignore argument.type
            'add a payout account' => fn() => $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0123456789'], []), // @phpstan-ignore argument.type
            'confirm a payout account' => fn() => $k->payoutAccounts->confirm('p1', '123456', []), // @phpstan-ignore argument.type
            'list payout accounts' => fn() => $k->payoutAccounts->list([]), // @phpstan-ignore argument.type
            'delete a payout account' => fn() => $k->payoutAccounts->del('p1', []), // @phpstan-ignore argument.type
            'get bank transfer details' => fn() => $k->checkoutSessions->bankTransfer('cs1', []), // @phpstan-ignore argument.type
        ];
        foreach ($calls as $name => $call) {
            $e = self::catching(\InvalidArgumentException::class, $call);
            self::assertStringContainsString('customerToken', $e->getMessage(), $name);
        }
        self::assertCount(0, $t->requests);
    }

    #[TestDox('[SEC-001] rejects unknown request options, so a typo never drops the customer token silently')]
    public function testUnknownOptions(): void
    {
        $t = new MockTransport();
        $k = self::client($t);
        $e = self::catching(\InvalidArgumentException::class, fn() => $k->escrows->milestones->release('e1', 'm1', [], ['customer_token' => 'ctk']));
        self::assertStringContainsString('customer_token', $e->getMessage());
        self::catching(\InvalidArgumentException::class, fn() => $k->escrows->retrieve('e1', ['timeoutMs' => 'soon'])); // @phpstan-ignore argument.type
        self::catching(\InvalidArgumentException::class, fn() => $k->escrows->retrieve('e1', ['cancel' => true])); // @phpstan-ignore argument.type
        self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['apiKey' => 'sk_test_x', 'api_key' => 'sk_test_y']));
        self::assertCount(0, $t->requests);
    }

    #[TestDox('[REQ-004] leaves empty query values out and sends booleans as words')]
    public function testEmptyQuery(): void
    {
        $t = new MockTransport(self::page([], null), self::page([], null));
        $k = self::client($t);
        $k->escrows->list(['status' => null, 'external_reference' => '', 'limit' => 5]); // @phpstan-ignore argument.type (empty values on purpose)
        $k->events->list(['flag' => true]);
        self::assertSame('https://api.ketapay.com/v1/escrows?limit=5', $t->requests[0]->url);
        self::assertSame('https://api.ketapay.com/v1/events?flag=true', $t->requests[1]->url);
    }

    #[TestDox('[REQ-001] sends no idempotency key on DELETE, and an object body on POST without params')]
    public function testDeleteAndEmptyBody(): void
    {
        $t = new MockTransport(['body' => ['object' => 'webhook_endpoint', 'deleted' => true]], ['body' => []]);
        $k = self::client($t);
        self::assertTrue($k->webhookEndpoints->del('we1')->deleted);
        self::assertArrayNotHasKey('Idempotency-Key', $t->requests[0]->headers);
        self::assertNull($t->requests[0]->body);
        $k->escrows->milestones->release('e1', 'm1');
        self::assertSame('{}', $t->requests[1]->body);
    }

    #[TestDox('[ERR-004] raises an API error when a successful response is not JSON')]
    public function testNotJson(): void
    {
        $t = new MockTransport(['raw' => '<html>oops</html>']);
        $e = self::catching(APIError::class, fn() => self::client($t)->escrows->retrieve('e1'));
        self::assertSame('invalid_response', $e->code);
        self::assertSame(200, $e->statusCode);
    }

    #[TestDox('[OPS-003] defaults to 2 retries, a 30-second timeout and the production base URL')]
    public function testDefaults(): void
    {
        $t = new MockTransport(
            self::apiError(500, 'api_error', 'internal_error'),
            self::apiError(500, 'api_error', 'internal_error'),
            self::apiError(500, 'api_error', 'internal_error'),
        );
        $k = new KetaPay(['apiKey' => 'sk_test_x', 'transport' => $t, 'logLevel' => 'off']);
        $e = self::catching(APIError::class, fn() => $k->escrows->retrieve('e1'));
        self::assertSame(500, $e->statusCode);
        self::assertCount(3, $t->requests);
        self::assertStringStartsWith('https://api.ketapay.com/v1/', $t->requests[0]->url);
        self::assertSame(30_000, $t->requests[0]->timeoutMs);
    }

    #[TestDox('[OPS-001] debug logs carry method, path, status, request id and timing')]
    public function testDebugFields(): void
    {
        $logged = [];
        $t = new MockTransport(['body' => [], 'headers' => ['KetaPay-Request-Id' => 'req_7']]);
        self::client($t, ['logLevel' => 'debug', 'logger' => static function (string $level, string $message, array $context) use (&$logged): void {
            $logged[$message] = $context;
        }])->escrows->retrieve('e1');
        $response = $logged['[ketapay] response'] ?? [];
        self::assertSame('GET', $response['method'] ?? null);
        self::assertSame('/escrows/e1', $response['path'] ?? null);
        self::assertSame(200, $response['status'] ?? null);
        self::assertSame('req_7', $response['requestId'] ?? null);
        self::assertIsInt($response['ms'] ?? null);
    }

    #[TestDox('rejects a bad log level or logger')]
    public function testBadLogger(): void
    {
        self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['apiKey' => 'sk_test_x', 'logLevel' => 'loud'])); // @phpstan-ignore argument.type
        self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['apiKey' => 'sk_test_x', 'logger' => new \stdClass()]));
    }

    #[TestDox('writes to error_log when no logger is given')]
    public function testErrorLogFallback(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ketapay-log');
        $previous = ini_set('error_log', (string) $file);
        try {
            $t = new MockTransport(self::apiError(503, 'api_error', 'internal_error'), ['body' => []]);
            (new KetaPay(['apiKey' => 'sk_test_secret', 'transport' => $t]))->escrows->retrieve('e1');
            $log = (string) file_get_contents((string) $file);
            self::assertStringContainsString('[ketapay] retrying', $log);
            self::assertStringNotContainsString('sk_test_secret', $log);
        } finally {
            ini_set('error_log', (string) $previous);
            @unlink((string) $file);
        }
    }
}
