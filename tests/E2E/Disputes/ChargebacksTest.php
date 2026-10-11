<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Disputes;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class ChargebacksTest extends E2ETestCase
{
    #[TestDox('[E2E-011] a lost chargeback closes as lost and records chargeback.closed')]
    public function testLostChargeback(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        $k = self::ketapay();
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'open_chargeback']);
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'close_chargeback', 'chargeback_status' => 'lost']);
        self::assertSame('lost', $k->chargebacks->listForEscrow($escrow->id)->data[0]->status);
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'chargeback.closed')));
    }
}
