<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Escrows;

use KetaPay\Errors\PermissionError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class PermissionsTest extends E2ETestCase
{
    #[TestDox('[E2E-019] refuses the wrong party: the buyer cannot deliver, the seller cannot release')]
    public function testWrongParty(): void
    {
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $k = self::ketapay();
        $m = self::first($escrow);
        self::catching(PermissionError::class, fn() => $k->escrows->milestones->deliver($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($buyer)]));
        $k->escrows->milestones->deliver($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($seller)]);
        $e = self::catching(PermissionError::class, fn() => $k->escrows->milestones->release($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($seller)]));
        self::assertSame('customer_not_allowed', $e->code);
    }
}
