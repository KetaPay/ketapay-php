<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Disputes;

use KetaPay\Errors\ConflictError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class DisputesTest extends E2ETestCase
{
    #[TestDox('[E2E-009] dispute with uploaded evidence, seller concedes: resolved and refunded')]
    public function testConcede(): void
    {
        self::requireServed('disputes', 'files');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $buyerToken = self::tokenFor($buyer);
        $file = $k->files->create(['file' => 'evidence', 'filename' => 'evidence.txt', 'purpose' => 'dispute_evidence'], ['customerToken' => $buyerToken]);
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_received', 'description' => 'Nothing was delivered by the due date.', 'files' => [$file->id]], ['customerToken' => $buyerToken]);
        self::assertSame('open', $dispute->status);
        self::assertGreaterThan(0, count($k->disputes->listEvidence($dispute->id, ['customerToken' => $buyerToken])));
        $resolved = $k->disputes->concede($dispute->id, ['customerToken' => self::tokenFor($seller)]);
        self::assertSame('resolved', $resolved->status);
        self::assertSame('seller', $resolved->resolved_by);
        self::eventually(fn() => self::assertSame('refunded', $k->escrows->retrieve($escrow->id)->status));
    }

    #[TestDox('[E2E-010] a dispute KetaPay resolves with a partial refund splits the money')]
    public function testPartialRefund(): void
    {
        self::requireServed('disputes', 'simulate');
        ['escrow' => $escrow, 'buyer' => $buyer] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $k->disputes->create($escrow->id, ['milestone_id' => $m->id, 'reason' => 'incomplete', 'description' => 'Only half of the design was delivered.'], ['customerToken' => self::tokenFor($buyer)]);
        $k->testHelpers->escrows->simulate($escrow->id, ['action' => 'resolve_dispute', 'outcome' => 'partially_refunded', 'refunded_amount' => intdiv($m->amount, 2)]);
        $dispute = $k->disputes->listForEscrow($escrow->id)->data[0];
        self::assertSame('partially_refunded', $dispute->outcome);
        self::assertSame('ketapay', $dispute->resolved_by);
        self::eventually(fn() => self::assertCount(1, self::eventsFor($escrow, 'dispute.resolved')));
    }

    #[TestDox('[E2E-030] an escalated dispute is under review and can no longer be settled')]
    public function testEscalate(): void
    {
        self::requireServed('disputes');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $buyerToken = ['customerToken' => self::tokenFor($buyer)];
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_as_agreed', 'description' => 'The work does not match the brief at all.'], $buyerToken);
        $escalated = $k->disputes->escalate($dispute->id, $buyerToken);
        self::assertSame('under_review', $escalated->status);
        self::assertNull($escalated->outcome);
        $e = self::catching(ConflictError::class, fn() => $k->disputes->concede($dispute->id, ['customerToken' => self::tokenFor($seller)]));
        self::assertSame('escrow_under_review', $e->code);
        self::assertSame('disputed', $k->escrows->retrieve($escrow->id)->status);
    }

    #[TestDox('[E2E-021] an open dispute blocks the normal release')]
    public function testDisputeBlocksRelease(): void
    {
        self::requireServed('disputes');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        $k->escrows->milestones->deliver($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($seller)]);
        $buyerToken = self::tokenFor($buyer);
        $k->disputes->create($escrow->id, ['milestone_id' => $m->id, 'reason' => 'not_as_agreed', 'description' => 'Not what the brief asked for.'], ['customerToken' => $buyerToken]);
        $e = self::catching(ConflictError::class, fn() => $k->escrows->milestones->release($escrow->id, $m->id, [], ['customerToken' => $buyerToken]));
        self::assertSame('dispute_open', $e->code);
    }
}
