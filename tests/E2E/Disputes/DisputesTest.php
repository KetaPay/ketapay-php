<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Disputes;

use KetaPay\Errors\ConflictError;
use KetaPay\Errors\PermissionError;
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
        $file = $k->files->create(['file' => '%PDF-1.4 evidence', 'filename' => 'evidence.pdf', 'contentType' => 'application/pdf', 'purpose' => 'dispute_evidence'], ['customerToken' => $buyerToken]);
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_received', 'description' => 'Nothing was delivered by the due date.', 'files' => [$file->id]], ['customerToken' => $buyerToken]);
        self::assertSame('open', $dispute->status);
        self::assertGreaterThan(0, count($k->disputes->listEvidence($dispute->id, ['customerToken' => $buyerToken])));
        $resolved = $k->disputes->concede($dispute->id, ['customerToken' => self::tokenFor($seller)]);
        self::assertSame('resolved', $resolved->status);
        self::assertSame('seller', $resolved->resolved_by);
        self::eventually(fn() => self::assertSame('refunded', $k->escrows->retrieve($escrow->id)->status));
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

    #[TestDox("[E2E-034] a dispute reads back by id and in the escrow's list, with the other side's evidence")]
    public function testReadBackAndEvidence(): void
    {
        self::requireServed('disputes');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $buyerToken = ['customerToken' => self::tokenFor($buyer)];
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_received', 'description' => 'Nothing has arrived and the date has passed.'], $buyerToken);
        self::assertSame($dispute->id, $k->disputes->retrieve($dispute->id)->id);
        $ids = array_map(static fn($d) => $d->id, $k->disputes->listForEscrow($escrow->id)->data);
        self::assertContains($dispute->id, $ids);

        $statement = 'It was sent on time, here is what happened.';
        $evidence = $k->disputes->submitEvidence($dispute->id, ['statement' => $statement], ['customerToken' => self::tokenFor($seller)]);
        self::assertSame($statement, $evidence->statement);
        $all = array_map(static fn($e) => $e->id, $k->disputes->listEvidence($dispute->id, $buyerToken)->data);
        self::assertContains($evidence->id, $all);
    }

    #[TestDox('[E2E-035] the party who opened a dispute withdraws it and the escrow carries on')]
    public function testWithdraw(): void
    {
        self::requireServed('disputes');
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $buyerToken = ['customerToken' => self::tokenFor($buyer)];
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_as_agreed', 'description' => 'The work does not match the brief at all.'], $buyerToken);
        self::catching(PermissionError::class, fn() => $k->disputes->withdraw($dispute->id, ['customerToken' => self::tokenFor($seller)]));
        $withdrawn = $k->disputes->withdraw($dispute->id, $buyerToken);
        self::assertSame('resolved', $withdrawn->status);
        self::assertSame('withdrawn', $withdrawn->outcome);
        self::assertSame('active', $k->escrows->retrieve($escrow->id)->status);
    }
}
