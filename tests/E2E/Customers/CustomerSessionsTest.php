<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Customers;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class CustomerSessionsTest extends E2ETestCase
{
    #[TestDox('[E2E-002] sends a code and exchanges the sandbox code for a customer token')]
    public function testCustomerToken(): void
    {
        $email = self::people()['buyer']['email'];
        $k = self::ketapay();
        $session = $k->customerSessions->create(['email' => $email]);
        self::assertSame('code_sent', $session->status);
        self::assertSame('email', $session->channel);
        $token = $k->customerSessions->verify($session->id, Env::SANDBOX_CODE);
        self::assertStringStartsWith('ctk_test_', (string) $token->token);
        self::assertGreaterThan(time(), strtotime((string) $token->expires_at));
    }
}
