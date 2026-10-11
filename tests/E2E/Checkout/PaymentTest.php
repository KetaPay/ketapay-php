<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Checkout;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class PaymentTest extends E2ETestCase
{
    #[TestDox('[E2E-004] paying funds the escrow, completes the session and records escrow.funded')]
    public function testPay(): void
    {
        ['escrow' => $escrow, 'session' => $session] = self::fundedEscrow();
        self::assertSame('complete', $session->status);
        self::assertSame('active', $escrow->status);
        foreach ($escrow->milestones as $milestone) {
            self::assertSame('funded', $milestone->status);
        }
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'escrow.funded')));
    }
}
