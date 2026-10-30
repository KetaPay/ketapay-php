<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\KetaPay;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class HttpClientTest extends TestCase
{
    #[TestDox('[AUTH-001] accepts test and live secret keys and reports livemode')]
    public function testKeys(): void
    {
        self::assertFalse((new KetaPay('sk_test_x'))->livemode);
        self::assertTrue((new KetaPay('sk_live_x'))->livemode);
    }

    #[TestDox('[AUTH-002] [AUTH-003] rejects publishable keys and anything that is not a secret key')]
    public function testBadKeys(): void
    {
        foreach (['pk_test_x' => '/publishable/', 'abc' => '/sk_test_ or sk_live_/', '' => '/apiKey is required/'] as $key => $message) {
            $e = self::catching(\InvalidArgumentException::class, fn() => new KetaPay((string) $key));
            self::assertMatchesRegularExpression($message, $e->getMessage());
        }
        $e = self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['baseUrl' => 'https://x.test'])); // @phpstan-ignore argument.type
        self::assertMatchesRegularExpression('/apiKey is required/', $e->getMessage());
    }

    #[TestDox('[AUTH-005] applies options given after the key, or in one config array, and refuses a key given twice')]
    public function testKeyWithOptions(): void
    {
        $t = new MockTransport(['body' => ['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null]], ['body' => ['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null]]);
        $baseUrl = 'http://localhost:4010';
        (new KetaPay('sk_test_123', ['baseUrl' => $baseUrl, 'transport' => $t]))->events->list();
        (new KetaPay(['apiKey' => 'sk_test_123', 'baseUrl' => $baseUrl, 'transport' => $t]))->events->list();
        self::assertSame([$baseUrl . '/events', $baseUrl . '/events'], array_map(static fn($r) => $r->url, $t->requests));

        $e = self::catching(\InvalidArgumentException::class, fn() => new KetaPay('sk_test_123', ['apiKey' => 'sk_test_other']));
        self::assertMatchesRegularExpression('/key is given twice/', $e->getMessage());
        $e = self::catching(\InvalidArgumentException::class, fn() => new KetaPay(['apiKey' => 'sk_test_123'], ['baseUrl' => $baseUrl]));
        self::assertMatchesRegularExpression('/inside the config array/', $e->getMessage());
    }

    #[TestDox('[AUTH-004] [REQ-001] [REQ-003] sends the key, an idempotency key on POST and a versioned user agent')]
    public function testHeaders(): void
    {
        $t = new MockTransport(['body' => ['id' => 'cs_1']]);
        self::client($t)->checkoutSessions->cancel('cs_1');
        $r = $t->last();
        self::assertSame('https://api.ketapay.com/v1/checkout_sessions/cs_1/cancel', $r->url);
        self::assertSame('POST', $r->method);
        self::assertSame('Bearer sk_test_123', $r->headers['Authorization']);
        self::assertSame('application/json', $r->headers['Accept']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $r->headers['Idempotency-Key']);
        self::assertSame('ketapay-php/' . KetaPay::VERSION . ' php/' . PHP_VERSION, $r->headers['User-Agent']);
    }

    #[TestDox('[REQ-003] appends appInfo to the User-Agent, and refuses one that could break the header')]
    public function testAppInfo(): void
    {
        $t = new MockTransport(['body' => []]);
        self::client($t, ['appInfo' => ['name' => 'ketapay-laravel', 'version' => '0.1.0-beta.1']])->escrows->retrieve('e1');
        self::assertSame('ketapay-php/' . KetaPay::VERSION . ' php/' . PHP_VERSION . ' ketapay-laravel/0.1.0-beta.1', $t->last()->headers['User-Agent']);
        foreach ([['name' => "evil\r\nX-Injected: 1", 'version' => '1'], ['name' => 'ok', 'version' => '1 2'], ['name' => 'ok'], 'ketapay-laravel/1'] as $bad) {
            self::catching(\InvalidArgumentException::class, fn() => self::client($t, ['appInfo' => $bad]));
        }
    }

    #[TestDox('[REQ-001] [REQ-002] uses the idempotency key you pass, and none on GET')]
    public function testIdempotencyKey(): void
    {
        $t = new MockTransport(['body' => []], ['body' => []]);
        $k = self::client($t);
        $k->checkoutSessions->cancel('cs_1', ['idempotencyKey' => 'order-42']);
        $k->escrows->retrieve('e1');
        self::assertSame('order-42', $t->requests[0]->headers['Idempotency-Key']);
        self::assertArrayNotHasKey('Idempotency-Key', $t->requests[1]->headers);
    }

    #[TestDox('[SEC-001] [REQ-006] sends the customer token, default and per-call headers, but never lets them replace auth')]
    public function testHeaderSafety(): void
    {
        $t = new MockTransport(['body' => []]);
        self::client($t, ['defaultHeaders' => ['X-Trace' => 'a', 'Authorization' => 'Bearer stolen', 'idempotency-key' => 'fixed']])
            ->disputes->concede('dp_1', ['customerToken' => 'ctk_test_9', 'headers' => ['X-Call' => 'b', 'KetaPay-Customer-Token' => 'forged', 'user-agent' => 'x']]);
        $headers = $t->last()->headers;
        self::assertSame('ctk_test_9', $headers['KetaPay-Customer-Token']);
        self::assertSame('a', $headers['X-Trace']);
        self::assertSame('b', $headers['X-Call']);
        self::assertSame('Bearer sk_test_123', $headers['Authorization']);
        $lower = array_change_key_case($headers);
        self::assertNotSame('fixed', $lower['idempotency-key']);
        self::assertStringStartsWith('ketapay-php/', $lower['user-agent']);
        self::assertCount(count($headers), $lower, 'no header is sent twice under different casing');
    }

    #[TestDox('[REQ-004] [SEC-003] encodes query parameters and ids')]
    public function testUrls(): void
    {
        $t = new MockTransport(self::page([], null), ['body' => []]);
        $k = self::client($t);
        $k->escrows->list(['status' => 'active', 'external_reference' => 'a b&c', 'limit' => 5]);
        $k->escrows->retrieve('../webhook_endpoints');
        self::assertSame('https://api.ketapay.com/v1/escrows?status=active&external_reference=a+b%26c&limit=5', $t->requests[0]->url);
        self::assertSame('https://api.ketapay.com/v1/escrows/..%2Fwebhook_endpoints', $t->requests[1]->url);
    }

    #[TestDox('[REQ-005] honours a custom base URL')]
    public function testBaseUrl(): void
    {
        $t = new MockTransport(['body' => []]);
        self::client($t, ['baseUrl' => 'http://localhost:4010/'])->escrows->retrieve('e1');
        self::assertSame('http://localhost:4010/escrows/e1', $t->last()->url);
    }

    #[TestDox('[ERR-005] gives the raw response and request id on success')]
    public function testRawResponse(): void
    {
        $t = new MockTransport(['body' => ['id' => 'e1'], 'headers' => ['KetaPay-Request-Id' => 'req_123', 'X-RateLimit-Remaining' => '99']]);
        $escrow = self::client($t)->escrows->retrieve('e1');
        self::assertSame('e1', $escrow->id);
        $response = $escrow->getLastResponse();
        self::assertNotNull($response);
        self::assertSame('req_123', $response->requestId);
        self::assertSame(200, $response->statusCode);
        self::assertSame('99', $response->header('x-ratelimit-remaining'));
        self::assertSame('{"id":"e1"}', $response->body);
    }

    #[TestDox('[SEC-004] [OPS-001] logs requests at debug without the key, the customer token or the body')]
    public function testDebugLogsAreClean(): void
    {
        $logged = [];
        $logger = static function (string $level, string $message, array $context) use (&$logged): void {
            $logged[] = [$level, $message, $context];
        };
        $t = new MockTransport(['body' => []]);
        self::client($t, ['logLevel' => 'debug', 'logger' => $logger])
            ->disputes->create('e1', ['reason' => 'damaged', 'description' => 'secret reason'], ['customerToken' => 'ctk_test_9']);
        self::assertNotEmpty($logged);
        self::assertSame('debug', $logged[0][0]);
        self::assertDoesNotMatchRegularExpression('/sk_test_123|ctk_test_9|secret reason/', json_encode($logged, JSON_THROW_ON_ERROR));
    }

    #[TestDox('[SEC-004] retry warnings and PSR-3 loggers never see secrets either')]
    public function testWarnLogsAreClean(): void
    {
        $logger = new class {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $lines = [];

            /** @param array<string, mixed> $context */
            public function log(string $level, string $message, array $context = []): void
            {
                $this->lines[] = [$level, $message, $context];
            }
        };
        $t = new MockTransport(self::apiError(500, 'api_error', 'internal_error'), ['body' => []]);
        self::client($t, ['logLevel' => 'debug', 'logger' => $logger])
            ->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0123456789'], ['customerToken' => 'ctk_test_9']);
        self::assertContains('warning', array_column($logger->lines, 0));
        self::assertDoesNotMatchRegularExpression('/sk_test_123|ctk_test_9|0123456789/', json_encode($logger->lines, JSON_THROW_ON_ERROR));
    }

    #[TestDox('[OPS-002] logs nothing at the default level on success')]
    public function testQuietByDefault(): void
    {
        $logged = [];
        $t = new MockTransport(['body' => []]);
        self::client($t, ['logLevel' => 'warn', 'logger' => static function () use (&$logged): void {
            $logged[] = func_get_args();
        }])->escrows->retrieve('e1');
        $k = new KetaPay(['apiKey' => 'sk_test_x', 'transport' => new MockTransport(['body' => []]), 'logger' => static function () use (&$logged): void {
            $logged[] = func_get_args();
        }]);
        $k->escrows->retrieve('e1');
        self::assertSame([], $logged);
    }
}
