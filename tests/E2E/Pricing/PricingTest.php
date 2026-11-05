<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Pricing;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class PricingTest extends E2ETestCase
{
    #[TestDox("[E2E-031] returns the caller's plan: its fee, VAT, who pays and the escrow sizes it covers")]
    public function testRetrieve(): void
    {
        $pricing = self::ketapay()->pricing->retrieve();
        self::assertSame('pricing', $pricing->object);
        self::assertIsString($pricing->plan);
        self::assertGreaterThan(0, $pricing->fee_bps);
        self::assertGreaterThan(0, $pricing->vat_bps);
        self::assertIsString($pricing->fees_paid_by);
        self::assertIsInt($pricing->min_amount);
    }
}
