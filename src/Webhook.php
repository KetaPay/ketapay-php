<?php

declare(strict_types=1);

namespace KetaPay;

use KetaPay\Errors\WebhookSignatureError;
use KetaPay\Models\Event;

/** Verifies incoming webhooks. Needs no API key. */
final class Webhook
{
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verifies a webhook delivery and returns the parsed event. Pass the RAW request body exactly as
     * received (`file_get_contents('php://input')`, or `$request->getContent()` in Laravel), the
     * `KetaPay-Signature` header, and your endpoint's signing secret. While rotating secrets, pass both
     * secrets as an array.
     *
     * @param string|list<string> $secret
     * @param int                 $toleranceSeconds oldest signature to accept
     * @param int|null            $now              Unix seconds, to override the clock in tests
     *
     * @throws WebhookSignatureError when the request must be refused
     */
    public static function verify(string $payload, ?string $signatureHeader, string|array $secret, int $toleranceSeconds = self::DEFAULT_TOLERANCE, ?int $now = null): Event
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            throw self::fail('missing_signature', 'No KetaPay-Signature header');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2 || $pair[1] === '') {
                continue;
            }
            if ($pair[0] === 't' && ctype_digit($pair[1])) {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }
        if ($timestamp === null || $timestamp === 0 || $signatures === []) {
            throw self::fail('malformed_signature', 'KetaPay-Signature header is malformed');
        }

        if (abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            throw self::fail('timestamp_outside_tolerance', 'Webhook timestamp is too old or in the future');
        }

        $secrets = is_array($secret) ? $secret : [$secret];
        if ($secrets === [] || in_array('', $secrets, true)) {
            throw new \InvalidArgumentException('KetaPay: a webhook secret is required');
        }
        $signed = "$timestamp.$payload";
        $matched = false;
        foreach ($secrets as $s) {
            $expected = hash_hmac('sha256', $signed, $s);
            foreach ($signatures as $candidate) {
                // Every pair is compared, in constant time, so timing reveals nothing about which matched.
                $matched = hash_equals($expected, strtolower($candidate)) || $matched;
            }
        }
        if (!$matched) {
            throw self::fail('signature_mismatch', 'Webhook signature does not match');
        }

        try {
            $event = json_decode($payload, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            $event = null;
        }
        if (!$event instanceof \stdClass) {
            throw self::fail('invalid_payload', 'Webhook body is not valid JSON');
        }

        return Event::constructFrom($event);
    }

    /** Builds a `KetaPay-Signature` header. For your tests only. */
    public static function generateTestSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();

        return "t=$t,v1=" . hash_hmac('sha256', "$t.$payload", $secret);
    }

    private static function fail(string $code, string $message): WebhookSignatureError
    {
        return new WebhookSignatureError($message, 'webhook_signature_error', $code);
    }
}
