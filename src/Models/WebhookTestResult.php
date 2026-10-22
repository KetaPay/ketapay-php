<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * WebhookTestResult
 *
 * @property-read string $object
 * @property-read string $event_id
 * @property-read bool $delivered True when your endpoint answered 2xx within 10 seconds
 * @property-read int|null $status_code
 * @property-read int|null $response_ms
 * @property-read string|null $error Why it failed, when no status code came back (timeout, TLS, DNS)
 */
final class WebhookTestResult extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'webhook_test_result'];
}
