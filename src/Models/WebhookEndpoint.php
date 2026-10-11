<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * WebhookEndpoint
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $url
 * @property-read list<string> $enabled_events
 * @property-read string|null $description
 * @property-read string $status `failing`: deliveries have failed for 3 days; fix the endpoint and re-enable it in the dashboard.
 * @property-read \KetaPay\KetaPayObject $metadata
 * @property-read string $created_at
 */
final class WebhookEndpoint extends \KetaPay\KetaPayObject
{
}
