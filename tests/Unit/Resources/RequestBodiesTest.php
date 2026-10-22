<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Resources;

use KetaPay\Models\BusinessSeller;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class RequestBodiesTest extends TestCase
{
    #[TestDox('[REQ-007] sends one-time codes in the body')]
    public function testCodes(): void
    {
        $t = new MockTransport(['body' => []], ['body' => []]);
        $k = self::client($t);
        $k->customerSessions->verify('cus_1', '123456');
        self::assertSame(['code' => '123456'], $t->lastJson());
        $k->payoutAccounts->confirm('pa_1', '654321', ['customerToken' => 't']);
        self::assertSame(['code' => '654321'], $t->lastJson());
        self::assertStringNotContainsString('654321', $t->last()->url);
    }

    #[TestDox('[REQ-007] sends the release code for an in-person release')]
    public function testReleaseCode(): void
    {
        $t = new MockTransport(['body' => []]);
        self::client($t)->escrows->milestones->release('e1', 'm1', ['release_code' => '731045']);
        self::assertSame(['release_code' => '731045'], $t->lastJson());
        self::assertSame('application/json', $t->last()->headers['Content-Type']);
    }

    #[TestDox('[REQ-009] creates a checkout session without a seller, sending no seller (the business sells it)')]
    public function testBusinessOwnSale(): void
    {
        $t = new MockTransport(['body' => ['object' => 'checkout_session', 'seller' => ['type' => 'business', 'name' => 'Ade Gadgets']]]);
        $session = self::client($t)->checkoutSessions->create([
            'amount' => 2_500_000,
            'title' => 'Desk lamp',
            'description' => 'Brass desk lamp with a warm LED bulb, boxed.',
            'buyer' => ['email' => 'ada@example.com'],
        ]);
        self::assertArrayNotHasKey('seller', $t->lastJson());
        self::assertInstanceOf(BusinessSeller::class, $session->seller);
    }

    #[TestDox('[REQ-010] delivers without a customer token on the business own sale, sending no token header')]
    public function testDeliverWithKeyAlone(): void
    {
        $t = new MockTransport(['body' => ['object' => 'escrow']]);
        self::client($t)->escrows->milestones->deliver('e1', 'm1', ['note' => 'Shipped with GIG']);
        self::assertArrayNotHasKey('KetaPay-Customer-Token', $t->last()->headers);
        self::assertSame(['note' => 'Shipped with GIG'], $t->lastJson());
    }

    #[TestDox('[REQ-008] creates a webhook endpoint from a URL alone, leaving the events to the API default')]
    public function testWebhookEndpointDefaultEvents(): void
    {
        $t = new MockTransport(['body' => ['id' => 'we_1', 'object' => 'webhook_endpoint', 'secret' => 'whsec_1']]);
        self::client($t)->webhookEndpoints->create(['url' => 'https://example.com/hooks']);
        self::assertSame(['url' => 'https://example.com/hooks'], $t->lastJson());
    }

    #[TestDox('sends params as JSON exactly as given, with unicode and slashes intact')]
    public function testJson(): void
    {
        $t = new MockTransport(['body' => []]);
        self::client($t)->checkoutSessions->create(['title' => 'Café ₦', 'images' => [['url' => 'https://x.test/a.jpg']], 'amount' => 45_000_000]); // @phpstan-ignore argument.type (partial body on purpose)
        self::assertSame('{"title":"Café ₦","images":[{"url":"https://x.test/a.jpg"}],"amount":45000000}', $t->last()->body);
    }
}
