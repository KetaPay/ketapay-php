<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\Event;
use KetaPay\Webhook;

/** Signature verification for incoming webhooks, reachable from the client. Same as the static `Webhook` class. */
final class Webhooks
{
    /**
     * @param string|list<string> $secret
     *
     * @see Webhook::verify()
     */
    public function verify(string $payload, ?string $signatureHeader, string|array $secret, int $toleranceSeconds = Webhook::DEFAULT_TOLERANCE, ?int $now = null): Event
    {
        return Webhook::verify($payload, $signatureHeader, $secret, $toleranceSeconds, $now);
    }

    /** @see Webhook::generateTestSignatureHeader() */
    public function generateTestSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        return Webhook::generateTestSignatureHeader($payload, $secret, $timestamp);
    }
}
