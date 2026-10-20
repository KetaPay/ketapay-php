<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\KetaPayObject;
use KetaPay\Models\BusinessSeller;
use KetaPay\Models\CheckoutSession;
use KetaPay\Models\Delivery;
use KetaPay\Models\Escrow;
use KetaPay\Models\Event;
use KetaPay\Models\Fees;
use KetaPay\Models\Milestone;
use KetaPay\Models\Party;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class ObjectsTest extends TestCase
{
    #[TestDox('hydrates nested objects into their models')]
    public function testNested(): void
    {
        $escrow = Escrow::constructFrom([
            'id' => 'e1',
            'object' => 'escrow',
            'metadata' => ['order' => '7'],
            'milestones' => [['object' => 'milestone', 'id' => 'm1', 'delivery' => ['note' => 'done'], 'payout' => null]],
        ]);
        self::assertInstanceOf(Milestone::class, $escrow->milestones[0]);
        self::assertInstanceOf(Delivery::class, $escrow->milestones[0]->delivery);
        self::assertNull($escrow->milestones[0]->payout);
        self::assertInstanceOf(KetaPayObject::class, $escrow->metadata);
        self::assertSame('7', $escrow->metadata['order']);
        self::assertSame('m1', $escrow->milestones[0]['id']);
    }

    #[TestDox('hydrates a field that holds one of several models into the one its data matches')]
    public function testOneOfModels(): void
    {
        $person = Escrow::constructFrom(['object' => 'escrow', 'seller' => ['email' => 'tunde@example.com', 'name' => 'Tunde']]);
        self::assertInstanceOf(Party::class, $person->seller);
        $business = Escrow::constructFrom(['object' => 'escrow', 'seller' => ['type' => 'business', 'name' => 'Ade Gadgets']]);
        self::assertInstanceOf(BusinessSeller::class, $business->seller);
        self::assertSame('Ade Gadgets', $business->seller->name);
        $session = CheckoutSession::constructFrom(['object' => 'checkout_session', 'seller' => ['email' => 'tunde@example.com']]);
        self::assertNotInstanceOf(BusinessSeller::class, $session->seller);
        self::assertSame('tunde@example.com', $session->seller['email']);
    }

    #[TestDox('types objects without a schema link by their object field, and keeps unknown fields')]
    public function testObjectMap(): void
    {
        $event = Event::constructFrom(['object' => 'event', 'type' => 'escrow.funded', 'data' => ['object' => ['object' => 'escrow', 'id' => 'e1', 'brand_new_field' => 3]]]);
        self::assertInstanceOf(Escrow::class, $event->data->object);
        // Fields added to the API after this version: readable by key (and by name, outside static analysis).
        self::assertSame(3, $event->data->object['brand_new_field']);
        self::assertSame(3, $event->data->object->brand_new_field); // @phpstan-ignore property.notFound
        self::assertNull($event->data->object['not_there']);
        self::assertFalse(isset($event->data->object['not_there']));
    }

    #[TestDox('hydrates objects that have no object field from the schema')]
    public function testSchemaNested(): void
    {
        $t = new MockTransport(['body' => ['object' => 'checkout_session', 'id' => 'cs1', 'fees' => ['amount' => 100, 'buyer_fee' => 2]]]);
        $session = self::client($t)->checkoutSessions->retrieve('cs1');
        self::assertInstanceOf(CheckoutSession::class, $session);
        self::assertInstanceOf(Fees::class, $session->fees);
        self::assertSame(102, $session->fees->amount + $session->fees->buyer_fee);
    }

    #[TestDox('is read only, and converts back to arrays and JSON')]
    public function testReadOnly(): void
    {
        $data = ['id' => 'e1', 'object' => 'escrow', 'milestones' => [['id' => 'm1', 'object' => 'milestone']], 'metadata' => []];
        $escrow = Escrow::constructFrom($data);
        self::assertSame($data, $escrow->toArray());
        self::assertSame(json_encode($data), json_encode($escrow));
        self::assertNull($escrow->getLastResponse());
        foreach ([
            fn() => $escrow->id = 'x', // @phpstan-ignore assign.propertyReadOnly
            function () use ($escrow): void {
                unset($escrow->id);
            },
            fn() => $escrow['id'] = 'x',
            function () use ($escrow): void {
                unset($escrow['id']);
            },
        ] as $write) {
            self::catching(\LogicException::class, $write);
        }
        self::assertTrue(isset($escrow['id']));
    }
}
