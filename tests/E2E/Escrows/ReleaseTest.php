<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Escrows;

use KetaPay\Errors\KetaPayError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class ReleaseTest extends E2ETestCase
{
    #[TestDox('[E2E-005] seller delivers, buyer releases: released with a payout and both events')]
    public function testDeliverAndRelease(): void
    {
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $delivered = $k->escrows->milestones->deliver($escrow->id, $m->id, ['note' => 'Designs ready'], ['customerToken' => self::tokenFor($seller)]);
        self::assertSame('delivered', $delivered->milestones[0]->status);
        $released = $k->escrows->milestones->release($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($buyer)]);
        self::assertSame('released', $released->milestones[0]->status);
        self::assertNotNull($released->milestones[0]->payout);
        self::eventually(function () use ($escrow): void {
            self::assertCount(1, self::eventsFor($escrow, 'milestone.delivered'));
            self::assertCount(1, self::eventsFor($escrow, 'milestone.released'));
        });
    }

    #[TestDox('[E2E-006] releases in person with the buyer code, and refuses a wrong code')]
    public function testReleaseCode(): void
    {
        ['escrow' => $escrow, 'buyer' => $buyer] = self::fundedEscrow(['release' => ['method' => 'release_code']]);
        $k = self::ketapay();
        $m = self::first($escrow);
        $code = (string) $k->escrows->milestones->releaseCode($escrow->id, $m->id, ['customerToken' => self::tokenFor($buyer)])->code;
        $wrong = $code === '000000' ? '111111' : '000000';
        $e = self::catching(KetaPayError::class, fn() => $k->escrows->milestones->release($escrow->id, $m->id, ['release_code' => $wrong]));
        self::assertSame('release_code_invalid', $e->code);
        self::assertSame('released', $k->escrows->milestones->release($escrow->id, $m->id, ['release_code' => $code])->milestones[0]->status);
    }

    #[TestDox('[E2E-007] auto-releases a delivered milestone when the window runs out')]
    public function testAutoRelease(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow(['release' => ['method' => 'buyer_confirms', 'auto_release_hours' => 48]]);
        $k = self::ketapay();
        $m = self::first($escrow);
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'deliver', 'milestone_id' => $m->id]);
        $after = $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'auto_release', 'milestone_id' => $m->id]);
        self::assertSame('released', $after->milestones[0]->status);
    }
}
