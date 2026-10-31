<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Escrows;

use KetaPay\Errors\KetaPayError;
use KetaPay\Models\BusinessSeller;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class BusinessSellerTest extends E2ETestCase
{
    #[TestDox('[E2E-025] no seller makes the business the seller; it delivers with its key, the buyer releases')]
    public function testOwnSale(): void
    {
        $who = self::people();
        $k = self::ketapay();
        $params = self::sessionParams($who);
        unset($params['seller']);
        $session = $k->checkoutSessions->create($params);
        self::assertInstanceOf(BusinessSeller::class, $session->seller);
        self::assertSame('business', $session->seller->type);

        $paid = $k->testHelpers->checkoutSessions->pay($session->id);
        $escrow = $k->escrows->retrieve((string) $paid->escrow_id);
        $buyer = ['customerToken' => self::tokenFor($who['buyer']['email'])];
        foreach ($escrow->milestones as $i => $m) {
            $delivered = $k->escrows->milestones->deliver($escrow->id, $m->id, ['note' => 'Shipped']);
            self::assertSame('delivered', $delivered->milestones[$i]->status);
            $escrow = $k->escrows->milestones->release($escrow->id, $m->id, [], $buyer);
        }
        self::assertSame('completed', $escrow->status);
        self::assertInstanceOf(BusinessSeller::class, $escrow->seller);
        self::assertSame($session->seller->name, $escrow->seller->name);
    }

    #[TestDox("[E2E-026] refuses commission on the business's own sale")]
    public function testCommissionRefused(): void
    {
        $params = self::sessionParams(null, ['commission' => ['rate_bps' => 200]]);
        unset($params['seller']);
        $e = self::catching(KetaPayError::class, fn() => self::ketapay()->checkoutSessions->create($params));
        self::assertSame('commission_not_allowed', $e->code);
    }

    #[TestDox("[E2E-027] refuses to deliver a sale between customers without the seller's token")]
    public function testDeliverNeedsToken(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        $e = self::catching(KetaPayError::class, fn() => self::ketapay()->escrows->milestones->deliver($escrow->id, self::first($escrow)->id));
        self::assertSame('customer_token_required', $e->code);
    }

    #[TestDox('[E2E-028] refunds an own sale with the key alone, and the money action can be read back')]
    public function testRefundOwnSale(): void
    {
        $escrowId = self::paidOwnSale();
        $k = self::ketapay();
        $action = $k->escrows->refund($escrowId, ['reason' => 'Out of stock, agreed with the customer.']);
        self::assertSame(['money_action', 'refund', 'executed'], [$action->object, $action->action, $action->status]);
        self::assertSame('refunded', $k->escrows->retrieve($escrowId)->status);
        $read = $k->moneyActions->retrieve($action->id);
        self::assertSame([$action->id, $action->status, $action->amount], [$read->id, $read->status, $read->amount]);
    }

    #[TestDox('[E2E-029] a key without escrows:manage cannot refund, and the escrow stays funded')]
    public function testRefundNeedsScope(): void
    {
        $restricted = Env::restrictedKey() ?? self::markTestSkipped('KETAPAY_E2E_RESTRICTED_SECRET_KEY not set');
        $escrowId = self::paidOwnSale();
        $e = self::catching(KetaPayError::class, fn() => self::ketapay($restricted)->escrows->refund($escrowId, ['reason' => 'Out of stock, agreed with the customer.']));
        self::assertSame('missing_scope', $e->code);
        self::assertSame('active', self::ketapay()->escrows->retrieve($escrowId)->status);
    }

    private static function paidOwnSale(): string
    {
        $params = self::sessionParams(self::people());
        unset($params['seller']);
        $k = self::ketapay();
        $session = $k->checkoutSessions->create($params);
        return (string) $k->testHelpers->checkoutSessions->pay($session->id)->escrow_id;
    }
}
