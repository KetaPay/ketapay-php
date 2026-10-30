<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Payouts;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class RefundsTest extends E2ETestCase
{
    #[TestDox("[E2E-013] a failed card refund moves the money to the buyer's balance")]
    public function testFailedRefund(): void
    {
        self::requireServed('disputes', 'simulate', 'milestone_payout');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_received', 'description' => 'Nothing was delivered by the due date.'], ['customerToken' => self::tokenFor($buyer)]);
        $k->disputes->concede($dispute->id, ['customerToken' => self::tokenFor($seller)]);
        $after = $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'fail_refund', 'milestone_id' => self::first($escrow)->id]);
        self::assertSame('ketapay_balance', $after->milestones[0]->refund?->destination);
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'refund.failed')));
    }
}
