<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Webhooks;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class EventsTest extends E2ETestCase
{
    #[TestDox('[E2E-018] the events list contains the events a flow produced')]
    public function testEvents(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        self::eventually(fn() => self::assertNotEmpty(self::eventsFor($escrow, 'escrow.funded')));
        $event = self::eventsFor($escrow, 'escrow.funded')[0];
        self::assertSame($event->id, self::ketapay()->events->retrieve($event->id)->id);
    }
}
