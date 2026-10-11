<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Support;

/**
 * E2E settings. CI reads them from Infisical (project ketapay-sdks, shared by every KetaPay SDK, environment staging).
 *
 *   KETAPAY_E2E_BASE_URL          staging API base URL, e.g. https://<staging>/v1   (required)
 *   KETAPAY_E2E_SECRET_KEY        sk_test_ key of the e2e organisation                (required)
 *   KETAPAY_E2E_OTHER_SECRET_KEY  sk_test_ key of a second organisation, for isolation tests
 *   KETAPAY_E2E_LIVE_SECRET_KEY   sk_live_ key on staging, for the live-key refusal test
 *   KETAPAY_E2E_WEBHOOK_URL       an HTTPS URL that answers 200, for webhook delivery tests
 */
final class Env
{
    /** In the sandbox every one-time code is this, and no message is sent. */
    public const SANDBOX_CODE = '123456';

    private static ?string $runId = null;

    public static function baseUrl(): ?string
    {
        return self::get('KETAPAY_E2E_BASE_URL');
    }

    public static function key(): ?string
    {
        return self::get('KETAPAY_E2E_SECRET_KEY');
    }

    public static function otherKey(): ?string
    {
        return self::get('KETAPAY_E2E_OTHER_SECRET_KEY');
    }

    public static function liveKey(): ?string
    {
        return self::get('KETAPAY_E2E_LIVE_SECRET_KEY');
    }

    public static function webhookUrl(): ?string
    {
        return self::get('KETAPAY_E2E_WEBHOOK_URL');
    }

    /** The suite runs only when the required settings exist. */
    public static function configured(): bool
    {
        return self::baseUrl() !== null && self::key() !== null;
    }

    /** Identifies this run: stamped on everything the suite creates, so staging can be cleaned up by run. */
    public static function runId(): string
    {
        return self::$runId ??= self::get('KETAPAY_E2E_RUN_ID') ?? 'e2e-php-' . base_convert((string) time(), 10, 36);
    }

    private static function get(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
