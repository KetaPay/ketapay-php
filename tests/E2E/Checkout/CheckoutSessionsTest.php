<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Checkout;

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
        $session = self::ketapay()->checkoutSessions->create(self::sessionParams($who, ['images' => [['url' => 'https://picsum.photos/seed/ketapay/600/400.jpg']]]));
        self::assertSame('open', $session->status);
        self::assertStringStartsWith('https://', (string) $session->url);
        self::assertCount(2, $session->milestones);
        self::assertCount(1, $session->images);
        $fees = $session->fees;
        self::assertSame($fees->amount + $fees->buyer_fee + $fees->buyer_vat, $fees->buyer_total);
        self::assertSame($fees->amount - $fees->seller_fee - $fees->commission, $fees->seller_payout);
    }

    #[TestDox("[E2E-003] returns bank transfer details for the buyer's token, and refuses someone else's")]
    public function testBankTransfer(): void
    {
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
}
