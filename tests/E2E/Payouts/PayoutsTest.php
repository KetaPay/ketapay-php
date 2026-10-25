<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Payouts;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class PayoutsTest extends E2ETestCase
{
    #[TestDox('[E2E-014] adds a payout account and confirms it with the code')]
    public function testPayoutAccount(): void
    {
        self::requireServed('payout_accounts');
        $k = self::ketapay();
        $options = ['customerToken' => self::tokenFor(self::people()['seller']['email'])];
        $pending = $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0000000000'], $options);
        self::assertSame('pending_confirmation', $pending->status);
        self::assertSame('active', $k->payoutAccounts->confirm($pending->id, Env::SANDBOX_CODE, $options)->status);
    }

    #[TestDox("[E2E-012] a failed payout records payout.failed and keeps the money in the seller's balance")]
    public function testFailedPayout(): void
    {
        self::requireServed('simulate', 'milestone_payout');
        ['escrow' => $escrow] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'deliver', 'milestone_id' => $m->id]);
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'release', 'milestone_id' => $m->id]);
        $after = $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'fail_payout', 'milestone_id' => $m->id]);
        self::assertSame('failed', $after->milestones[0]->payout?->status);
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'payout.failed')));
    }
}
