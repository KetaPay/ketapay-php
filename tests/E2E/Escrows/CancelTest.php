<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Escrows;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class CancelTest extends E2ETestCase
{
    #[TestDox('[E2E-008] the buyer cancels before delivery: escrow refunded and escrow.refunded recorded')]
    public function testCancel(): void
    {
        self::requireServed('escrow_cancel');
        ['escrow' => $escrow, 'buyer' => $buyer] = self::fundedEscrow();
        $cancelled = self::ketapay()->escrows->cancel($escrow->id, ['reason' => 'E2E'], ['customerToken' => self::tokenFor($buyer)]);
        self::assertSame('refunded', $cancelled->status);
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'escrow.refunded')));
    }
}
