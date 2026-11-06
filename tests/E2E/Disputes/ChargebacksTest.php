<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Disputes;

use KetaPay\Errors\NotFoundError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * A sandbox has no way to raise a chargeback (a bank starts one), so only the empty and unknown cases run
 * here. The chargeback.* webhooks are covered with test events.
 */
final class ChargebacksTest extends E2ETestCase
{
    #[TestDox('[E2E-037] an escrow with no chargeback lists none, and an unknown one is a 404')]
    public function testEmptyAndUnknown(): void
    {
        self::requireServed('chargebacks');
        ['escrow' => $escrow, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        self::assertSame([], $k->chargebacks->listForEscrow($escrow->id)->data);
        $unknown = sprintf('%s-4000-8000-%s', substr(bin2hex(random_bytes(6)), 0, 8) . '-' . substr(bin2hex(random_bytes(2)), 0, 4), bin2hex(random_bytes(6)));
        self::catching(NotFoundError::class, fn() => $k->chargebacks->retrieve($unknown));
        self::catching(NotFoundError::class, fn() => $k->chargebacks->submitEvidence($unknown, ['statement' => 'Delivered.'], ['customerToken' => self::tokenFor($seller)]));
    }
}
