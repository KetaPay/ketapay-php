<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * The new secret
 *
 * @property-read string $object
 * @property-read string $secret
 * @property-read string $previous_secret_expires_at
 */
final class WebhookSecret extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'webhook_secret'];
}
