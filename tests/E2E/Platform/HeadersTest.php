<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Platform;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class HeadersTest extends E2ETestCase
{
    #[TestDox('[E2E-016] responses carry a request id and rate-limit headers')]
    public function testHeaders(): void
    {
        $response = self::ketapay()->escrows->list(['limit' => 1])->getLastResponse();
        self::assertNotNull($response);
        self::assertNotEmpty($response->requestId);
        foreach (['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            self::assertNotEmpty($response->header($header), $header);
        }
    }
}
