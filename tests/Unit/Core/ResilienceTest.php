<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\CancellationToken;
use KetaPay\Errors\ConnectionError;
use KetaPay\Errors\RequestAbortedError;
use KetaPay\Http\CurlTransport;
use KetaPay\Http\StreamTransport;
use KetaPay\Http\Transport;
use KetaPay\Http\TransportException;
use KetaPay\KetaPay;
use KetaPay\Tests\Support\LocalServer;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestDox;

final class ResilienceTest extends TestCase
{
    #[TestDox('[RES-004] waits for X-RateLimit-Reset on 429 before retrying')]
    public function testRateLimitReset(): void
    {
        $reset = (int) ceil(microtime(true)) + 1;
        $t = new MockTransport(
            [...self::apiError(429, 'rate_limit_error', 'rate_limited'), 'headers' => ['X-RateLimit-Reset' => (string) $reset]],
            ['body' => ['ok' => true]],
        );
        $started = microtime(true);
        self::assertTrue(self::client($t)->escrows->retrieve('e1')['ok']);
        self::assertCount(2, $t->requests);
        self::assertGreaterThanOrEqual($reset - 0.05, microtime(true));
        self::assertLessThan(10, microtime(true) - $started);
    }

    #[TestDox('[RES-005] raises a connection error with code timeout when the transport times out')]
    public function testTimeoutMapping(): void
    {
        $t = new MockTransport(new TransportException('Operation timed out', timedOut: true));
        $e = self::catching(ConnectionError::class, fn() => self::client($t, ['maxRetries' => 0])->escrows->retrieve('e1', ['timeoutMs' => 50]));
        self::assertSame('timeout', $e->code);
        self::assertStringContainsString('50ms', $e->getMessage());
        self::assertSame(50, $t->last()->timeoutMs);
    }

    #[RequiresPhpExtension('curl')]
    #[TestDox('[RES-005] a real request that exceeds its timeout raises timeout (curl)')]
    public function testRealTimeoutCurl(): void
    {
        $this->assertTimesOut(new CurlTransport());
    }

    #[TestDox('[RES-005] a real request that exceeds its timeout raises timeout (streams)')]
    public function testRealTimeoutStreams(): void
    {
        $this->assertTimesOut(new StreamTransport());
    }

    #[TestDox('[RES-006] does not send at all when the token is already cancelled')]
    public function testAlreadyCancelled(): void
    {
        $t = new MockTransport(['body' => []]);
        $token = new CancellationToken();
        $token->cancel();
        self::catching(RequestAbortedError::class, fn() => self::client($t)->escrows->retrieve('e1', ['cancel' => $token]));
        self::assertCount(0, $t->requests);
    }

    #[TestDox('[RES-006] stops during the backoff wait when cancelled, without another attempt')]
    public function testCancelDuringBackoff(): void
    {
        $token = new CancellationToken();
        $t = new MockTransport(self::apiError(503, 'api_error', 'internal_error'), ['body' => []]);
        // Cancelled while the first attempt is answered, so the SDK finds out during the backoff wait.
        $t->onSend = static fn() => $token->cancel();
        $e = self::catching(RequestAbortedError::class, fn() => self::client($t)->escrows->retrieve('e1', ['cancel' => $token]));
        self::assertSame('aborted', $e->code);
        self::assertCount(1, $t->requests);
    }

    #[TestDox('[RES-006] a transport that reports cancellation raises an aborted error and is not retried')]
    public function testTransportCancelled(): void
    {
        $t = new MockTransport(new TransportException('aborted by callback', cancelled: true), ['body' => []]);
        self::catching(RequestAbortedError::class, fn() => self::client($t)->escrows->retrieve('e1', ['cancel' => new CancellationToken()]));
        self::assertCount(1, $t->requests);
    }

    #[RequiresPhpExtension('curl')]
    #[RequiresPhpExtension('pcntl')]
    #[TestDox('[RES-006] cancelling mid-transfer aborts the real request at once and stops retries (curl)')]
    public function testCancelMidTransfer(): void
    {
        $token = new CancellationToken();
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static fn() => $token->cancel());
        $k = new KetaPay(['apiKey' => 'sk_test_x', 'baseUrl' => LocalServer::url() . '/slow/5000', 'transport' => new CurlTransport(), 'logLevel' => 'off']);
        $started = microtime(true);
        pcntl_alarm(1);
        try {
            self::catching(RequestAbortedError::class, fn() => $k->escrows->retrieve('e1', ['cancel' => $token]));
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }
        self::assertLessThan(3, microtime(true) - $started);
    }

    private function assertTimesOut(Transport $transport): void
    {
        $k = new KetaPay(['apiKey' => 'sk_test_x', 'baseUrl' => LocalServer::url() . '/slow/3000', 'transport' => $transport, 'maxRetries' => 0, 'logLevel' => 'off']);
        $started = microtime(true);
        $e = self::catching(ConnectionError::class, fn() => $k->escrows->retrieve('e1', ['timeoutMs' => 300]));
        self::assertSame('timeout', $e->code);
        self::assertLessThan(2.5, microtime(true) - $started);
    }
}
