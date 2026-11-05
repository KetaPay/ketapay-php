<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Checkout;

use KetaPay\Errors\ConflictError;
use KetaPay\Errors\InvalidRequestError;
use KetaPay\Errors\PermissionError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class CheckoutSessionsTest extends E2ETestCase
{
    #[TestDox('[E2E-001] creates a session with milestones, images and both contacts, with correct fees')]
    public function testCreate(): void
    {
        $who = self::people();
        $who['buyer']['phone'] = '+2348012345678';
        $session = self::ketapay()->checkoutSessions->create(self::sessionParams($who, ['images' => [['url' => 'https://ketapay.com/logo.png']]]));
        self::assertSame('open', $session->status);
        self::assertStringStartsWith('https://', (string) $session->url);
        self::assertCount(2, $session->milestones);
        self::assertCount(1, $session->images);
        $fees = $session->fees;
        self::assertSame($fees->amount + $fees->buyer_fee + $fees->buyer_vat, $fees->buyer_total);
        self::assertSame($fees->amount - $fees->seller_fee - $fees->seller_vat - $fees->commission, $fees->seller_payout);
    }

    #[TestDox("[E2E-003] returns bank transfer details for the buyer's token, and refuses someone else's")]
    public function testBankTransfer(): void
    {
        self::requireServed('bank_transfer');
        $who = self::people();
        $k = self::ketapay();
        $session = $k->checkoutSessions->create(self::sessionParams($who));
        $details = $k->checkoutSessions->bankTransfer($session->id, ['customerToken' => self::tokenFor($who['buyer']['email'])]);
        self::assertMatchesRegularExpression('/^\d{10}$/', (string) $details->account_number);
        self::assertSame($session->fees->buyer_total, $details->amount);
        self::catching(PermissionError::class, fn() => $k->checkoutSessions->bankTransfer($session->id, ['customerToken' => self::tokenFor($who['seller']['email'])]));
    }

    #[TestDox('[E2E-022] refuses invalid input with the right param')]
    public function testInvalidInput(): void
    {
        $k = self::ketapay();
        $cases = [
            'buyer' => ['buyer' => ['name' => 'No contact']],
            'milestones' => ['milestones' => [['title' => 'Only part', 'amount' => 1]]],
            'description' => ['description' => 'Too short'],
        ];
        foreach ($cases as $param => $override) {
            $e = self::catching(InvalidRequestError::class, fn() => $k->checkoutSessions->create([...self::sessionParams(), ...$override]));
            self::assertStringContainsString($param, (string) $e->param, $param);
        }
    }

    #[TestDox("[E2E-032] refuses a session below the plan's minimum, and none is created")]
    public function testBelowMinimum(): void
    {
        self::assertRefusedAndNotCreated(199_900, 'amount_too_small');
    }

    #[TestDox("[E2E-033] refuses a session above the plan's maximum, and none is created")]
    public function testAboveMaximum(): void
    {
        self::assertRefusedAndNotCreated(5_000_000_100, 'amount_requires_custom_rate');
    }

    private static function assertRefusedAndNotCreated(int $amount, string $code): void
    {
        $k = self::ketapay();
        $title = 'E2E limit ' . $code . ' ' . bin2hex(random_bytes(8));
        $params = self::sessionParams(null, ['amount' => $amount, 'title' => $title, 'milestones' => [['title' => 'All of it', 'amount' => $amount]]]);
        $e = self::catching(InvalidRequestError::class, fn() => $k->checkoutSessions->create($params));
        self::assertSame($code, $e->code);
        $titles = array_map(static fn($s) => $s->title, $k->checkoutSessions->list(['limit' => 20])->data);
        self::assertNotContains($title, $titles);
    }

    #[TestDox('[E2E-038] a session reads back by id, and an open one can be cancelled once')]
    public function testCancel(): void
    {
        $k = self::ketapay();
        $session = $k->checkoutSessions->create(self::sessionParams());
        self::assertSame($session->id, $k->checkoutSessions->retrieve($session->id)->id);
        self::assertSame('cancelled', $k->checkoutSessions->cancel($session->id)->status);
        self::catching(ConflictError::class, fn() => $k->checkoutSessions->cancel($session->id));
    }
}
