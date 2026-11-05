<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Webhooks;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class WebhookEndpointsTest extends E2ETestCase
{
    #[TestDox('[E2E-017] creates an endpoint, delivers a test event, and rotates the secret')]
    public function testEndpoint(): void
    {
        $url = Env::webhookUrl() ?? self::markTestSkipped('KETAPAY_E2E_WEBHOOK_URL not set');
        $k = self::ketapay();
        $endpoint = $k->webhookEndpoints->create(['url' => $url, 'enabled_events' => ['escrow.funded']]);
        try {
            self::assertStringStartsWith('whsec_', (string) $endpoint->secret);
            self::assertFalse(isset($k->webhookEndpoints->retrieve($endpoint->id)->secret));
            self::assertTrue($k->webhookEndpoints->test($endpoint->id, 'escrow.funded')->delivered);
            $rotated = $k->webhookEndpoints->rotateSecret($endpoint->id);
            self::assertNotSame($endpoint->secret, $rotated->secret);
            self::assertGreaterThan(time(), strtotime((string) $rotated->previous_secret_expires_at));
        } finally {
            $k->webhookEndpoints->del($endpoint->id);
        }
    }

    #[TestDox('[E2E-041] an endpoint shows in the list, and an update changes its events')]
    public function testListAndUpdate(): void
    {
        $k = self::ketapay();
        $endpoint = $k->webhookEndpoints->create(['url' => 'https://example.com/ketapay-e2e-webhook', 'enabled_events' => ['escrow.funded']]);
        try {
            $ids = array_map(static fn($e) => $e->id, $k->webhookEndpoints->list(['limit' => 100])->data);
            self::assertContains($endpoint->id, $ids);
            $updated = $k->webhookEndpoints->update($endpoint->id, ['enabled_events' => ['escrow.refunded']]);
            self::assertSame(['escrow.refunded'], $updated->enabled_events);
        } finally {
            $k->webhookEndpoints->del($endpoint->id);
        }
    }
}
