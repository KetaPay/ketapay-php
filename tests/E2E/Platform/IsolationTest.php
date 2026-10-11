<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Platform;

use KetaPay\Errors\NotFoundError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class IsolationTest extends E2ETestCase
{
    #[TestDox("[E2E-020] one organisation's key cannot read another organisation's escrow")]
    public function testIsolation(): void
    {
        $other = Env::otherKey() ?? self::markTestSkipped('KETAPAY_E2E_OTHER_SECRET_KEY not set');
        ['escrow' => $escrow] = self::fundedEscrow();
        self::catching(NotFoundError::class, fn() => self::ketapay($other)->escrows->retrieve($escrow->id));
    }
}
