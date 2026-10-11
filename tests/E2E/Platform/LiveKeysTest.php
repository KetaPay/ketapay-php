<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Platform;

use KetaPay\Http\CurlTransport;
use KetaPay\Http\Request;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class LiveKeysTest extends E2ETestCase
{
    #[TestDox('[E2E-024] the API refuses test helpers for a live key')]
    public function testLiveKeyRefused(): void
    {
        $live = Env::liveKey() ?? self::markTestSkipped('KETAPAY_E2E_LIVE_SECRET_KEY not set');
        // The SDK refuses before sending (SEC-005), so call the API directly to prove the server refuses too.
        $response = (new CurlTransport())->send(new Request(
            'POST',
            Env::baseUrl() . '/test_helpers/escrows/00000000-0000-4000-8000-000000000000/simulate',
            ['Authorization' => "Bearer $live", 'Content-Type' => 'application/json', 'Idempotency-Key' => Env::runId() . '-live-' . bin2hex(random_bytes(4))],
            '{"action":"deliver"}',
            30_000,
        ));
        self::assertSame(404, $response->statusCode);
        $body = json_decode($response->body, true);
        self::assertIsArray($body);
        self::assertIsArray($body['error'] ?? null);
        self::assertSame('test_mode_only', $body['error']['code'] ?? null);
    }
}
