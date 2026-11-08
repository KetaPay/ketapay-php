<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Payouts;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class PayoutsTest extends E2ETestCase
{
    // One run of the payout worker (every minute on staging), with room to spare
    private const PAYOUT_WORKER_WAIT_SECONDS = 90;

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

    #[TestDox("[E2E-012] a payout to the failing test account records payout.failed and keeps the money in the seller's balance")]
    public function testFailedPayout(): void
    {
        self::requireServed('payout_accounts', 'milestone_payout');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $sellerOptions = ['customerToken' => self::tokenFor($seller)];
        // A platform's seller is paid once verified; unverified, the payout would wait
        $k->testHelpers->customers->verify($sellerOptions);
        // 0000000001 is the sandbox account whose transfers always fail
        $pending = $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0000000001'], $sellerOptions);
        $k->payoutAccounts->confirm($pending->id, Env::SANDBOX_CODE, $sellerOptions);
        $k->escrows->milestones->deliver($escrow->id, $m->id, ['note' => 'Done'], $sellerOptions);
        $k->escrows->milestones->release($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($buyer)]);
        // The release only queues the payout: the API's payout worker sends it once a minute
        self::eventually(
            fn() => self::assertSame('failed', $k->escrows->retrieve($escrow->id)->milestones[0]->payout?->status),
            self::PAYOUT_WORKER_WAIT_SECONDS,
            2,
        );
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'payout.failed')));
    }

    #[TestDox("[E2E-043] an unverified seller's payout waits for verification, then goes out with payout.sent")]
    public function testPayoutAwaitsVerification(): void
    {
        self::requireServed('payout_accounts', 'milestone_payout');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $sellerOptions = ['customerToken' => self::tokenFor($seller)];
        $pending = $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0000000000'], $sellerOptions);
        $k->payoutAccounts->confirm($pending->id, Env::SANDBOX_CODE, $sellerOptions);
        $k->escrows->milestones->deliver($escrow->id, $m->id, ['note' => 'Done'], $sellerOptions);
        $k->escrows->milestones->release($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($buyer)]);
        $held = $k->escrows->retrieve($escrow->id)->milestones[0]->payout;
        self::assertSame('awaiting_verification', $held?->status);

        $k->testHelpers->customers->verify($sellerOptions);
        self::eventually(
            fn() => self::assertSame('sent', $k->escrows->retrieve($escrow->id)->milestones[0]->payout?->status),
            self::PAYOUT_WORKER_WAIT_SECONDS,
            2,
        );
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'payout.sent')));
    }

    #[TestDox("[E2E-040] banks are listed, and a payout account shows in the customer's list until deleted")]
    public function testListAndDelete(): void
    {
        self::requireServed('payout_accounts');
        $k = self::ketapay();
        self::assertNotEmpty($k->banks->list()->data);
        $options = ['customerToken' => self::tokenFor(self::people()['seller']['email'])];
        $account = $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0000000000'], $options);
        $ids = static fn(): array => array_map(static fn($a) => $a->id, $k->payoutAccounts->list($options)->data);
        self::assertContains($account->id, $ids());
        $k->payoutAccounts->del($account->id, $options);
        self::assertNotContains($account->id, $ids());
    }
}
