<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Escrows;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class FlagTest extends E2ETestCase
{
    #[TestDox('[E2E-036] flagging an escrow records a flag on it with the reason given')]
    public function testFlag(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        $flag = self::ketapay()->escrows->flag($escrow->id, ['reason' => 'suspicious_activity', 'note' => 'Raised by the e2e suite.']);
        self::assertSame('flag', $flag->object);
        self::assertSame($escrow->id, $flag->escrow_id);
        self::assertSame('suspicious_activity', $flag->reason);
    }
}
