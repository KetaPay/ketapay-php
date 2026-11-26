<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Disputes;

use KetaPay\Errors\InvalidRequestError;
use KetaPay\Errors\PermissionError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

/** A platform decides a dispute between two of its customers, and only once one of them raised it. */
final class RulingsTest extends E2ETestCase
{
    #[TestDox('[E2E-043] a platform splits a dispute its buyer raised: part refunded, the rest released')]
    public function testSplit(): void
    {
        self::requireServed('disputes', 'rulings');
        ['escrow' => $escrow, 'buyer' => $buyer] = self::fundedEscrow();
        $k = self::ketapay();
        $dispute = $k->disputes->create($escrow->id, ['reason' => 'damaged', 'description' => 'The screen arrived cracked in the box.'], ['customerToken' => self::tokenFor($buyer)]);
        $refund = intdiv($escrow->amount * 3, 10);
        $action = $k->disputes->resolve($dispute->id, ['outcome' => 'split', 'refund_amount' => $refund, 'reason' => 'Cracked screen: our policy returns 30% for cosmetic damage.']);
        self::assertSame(['money_action', 'ruling_split', 'executed', $refund, $dispute->id], [$action->object, $action->action, $action->status, $action->refund_amount, $action->dispute_id]);
        $resolved = $k->disputes->retrieve($dispute->id);
        self::assertSame(['resolved', 'partially_refunded', 'platform', $refund], [$resolved->status, $resolved->outcome, $resolved->resolved_by, $resolved->refunded_amount]);
    }

    #[TestDox('[E2E-044] no dispute, no ruling: a refund is refused, and a split giving the buyer everything too')]
    public function testRefusals(): void
    {
        self::requireServed('disputes', 'rulings');
        ['escrow' => $escrow, 'buyer' => $buyer] = self::fundedEscrow();
        $k = self::ketapay();
        $e = self::catching(PermissionError::class, fn() => $k->escrows->refund($escrow->id, ['reason' => 'The platform wants to step in early.']));
        self::assertSame('dispute_required', $e->code);

        $dispute = $k->disputes->create($escrow->id, ['reason' => 'not_received', 'description' => 'Nothing has arrived after two weeks.'], ['customerToken' => self::tokenFor($buyer)]);
        $e = self::catching(InvalidRequestError::class, fn() => $k->disputes->resolve($dispute->id, ['outcome' => 'split', 'refund_amount' => $escrow->amount, 'reason' => 'Trying to give the buyer everything.']));
        self::assertSame('refund_amount', $e->param);
        self::assertSame('disputed', $k->escrows->retrieve($escrow->id)->status);
        self::assertSame('open', $k->disputes->retrieve($dispute->id)->status);
    }
}
