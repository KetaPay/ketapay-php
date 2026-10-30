<?php

declare(strict_types=1);

namespace KetaPay;

use KetaPay\Http\CurlTransport;
use KetaPay\Http\HttpClient;
use KetaPay\Http\Logger;
use KetaPay\Http\StreamTransport;
use KetaPay\Http\Transport;
use KetaPay\Resources\Banks;
use KetaPay\Resources\Chargebacks;
use KetaPay\Resources\CheckoutSessions;
use KetaPay\Resources\CustomerSessions;
use KetaPay\Resources\Disputes;
use KetaPay\Resources\Escrows;
use KetaPay\Resources\Events;
use KetaPay\Resources\Files;
use KetaPay\Resources\PayoutAccounts;
use KetaPay\Resources\TestHelpers;
use KetaPay\Resources\WebhookEndpoints;
use KetaPay\Resources\Webhooks;

/**
 * The KetaPay client. Build it once from your secret key and reuse it.
 *
 *     $ketapay = new KetaPay(getenv('KETAPAY_SECRET_KEY'));
 *     $ketapay = new KetaPay(['apiKey' => $key, 'maxRetries' => 3, 'logLevel' => 'debug', 'logger' => $psr3]);
 *
 * @phpstan-type Config array{apiKey: string, baseUrl?: string, maxRetries?: int, timeoutMs?: int, transport?: Transport, defaultHeaders?: array<string, string>, logLevel?: 'off'|'error'|'warn'|'info'|'debug', logger?: object|callable|null, appInfo?: array{name: string, version: string}}
 * @phpstan-type Options array{baseUrl?: string, maxRetries?: int, timeoutMs?: int, transport?: Transport, defaultHeaders?: array<string, string>, logLevel?: 'off'|'error'|'warn'|'info'|'debug', logger?: object|callable|null, appInfo?: array{name: string, version: string}}
 * @phpstan-type CustomerRequestOptions array{customerToken: string, idempotencyKey?: string, timeoutMs?: int, maxRetries?: int, cancel?: CancellationToken, headers?: array<string, string>}
 */
final class KetaPay
{
    public const VERSION = '0.1.0-beta.4'; // x-release-please-version

    private const CONFIG_KEYS = ['apiKey', 'baseUrl', 'maxRetries', 'timeoutMs', 'transport', 'defaultHeaders', 'logLevel', 'logger', 'appInfo'];

    // Checkout
    public readonly CheckoutSessions $checkoutSessions;
    // Customers
    public readonly CustomerSessions $customerSessions;
    // Escrows
    public readonly Escrows $escrows;
    // Disputes
    public readonly Disputes $disputes;
    public readonly Chargebacks $chargebacks;
    // Payouts
    public readonly PayoutAccounts $payoutAccounts;
    public readonly Banks $banks;
    // Files
    public readonly Files $files;
    // Webhooks
    public readonly WebhookEndpoints $webhookEndpoints;
    public readonly Events $events;
    /** Signature verification for incoming webhooks. Needs no API key. */
    public readonly Webhooks $webhooks;
    /** Sandbox only. */
    public readonly TestHelpers $testHelpers;
    /** True for `sk_live_` keys. */
    public readonly bool $livemode;

    /**
     * `new KetaPay($key, $options)` or `new KetaPay(['apiKey' => $key, ...$options])`. Options are never
     * dropped: a key given in both places, or options after a config array, is refused.
     *
     * @param string|Config $apiKeyOrConfig your secret key (`sk_test_...` or `sk_live_...`), or a config array
     * @param Options|null $options everything in the config but the key, after a key
     */
    public function __construct(string|array $apiKeyOrConfig, ?array $options = null)
    {
        $config = self::resolveConfig($apiKeyOrConfig, $options);
        $unknown = array_diff(array_keys($config), self::CONFIG_KEYS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('KetaPay: unknown config ' . implode(', ', $unknown) . '. Use ' . implode(', ', self::CONFIG_KEYS) . '.');
        }

        $apiKey = $config['apiKey'] ?? '';
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \InvalidArgumentException('KetaPay: apiKey is required');
        }
        if (str_starts_with($apiKey, 'pk_')) {
            throw new \InvalidArgumentException('KetaPay: publishable keys (pk_) only work with @ketapay/checkout. Use your secret key.');
        }
        if (!preg_match('/^sk_(test|live)_/', $apiKey)) {
            throw new \InvalidArgumentException('KetaPay: apiKey must start with sk_test_ or sk_live_');
        }
        $baseUrl = rtrim($config['baseUrl'] ?? HttpClient::DEFAULT_BASE_URL, '/');
        if (!self::isSafeBaseUrl($baseUrl)) {
            throw new \InvalidArgumentException('KetaPay: baseUrl must use https (plain http is only allowed for localhost)');
        }

        $http = new HttpClient(
            $apiKey,
            $baseUrl,
            $config['maxRetries'] ?? 2,
            $config['timeoutMs'] ?? 30_000,
            $config['transport'] ?? (extension_loaded('curl') ? new CurlTransport() : new StreamTransport()),
            $config['defaultHeaders'] ?? [],
            new Logger($config['logLevel'] ?? 'warn', $config['logger'] ?? null),
            self::appInfo($config['appInfo'] ?? null),
        );
        $this->livemode = $http->livemode;
        $this->checkoutSessions = new CheckoutSessions($http);
        $this->customerSessions = new CustomerSessions($http);
        $this->escrows = new Escrows($http);
        $this->disputes = new Disputes($http);
        $this->chargebacks = new Chargebacks($http);
        $this->payoutAccounts = new PayoutAccounts($http);
        $this->banks = new Banks($http);
        $this->files = new Files($http);
        $this->webhookEndpoints = new WebhookEndpoints($http);
        $this->events = new Events($http);
        $this->webhooks = new Webhooks();
        $this->testHelpers = new TestHelpers($http);
    }

    /**
     * "name/version" for the User-Agent. Libraries built on this SDK (like ketapay-laravel) pass it so
     * KetaPay can tell integrations apart. Only token characters, so it can never break the header.
     */
    private static function appInfo(mixed $info): ?string
    {
        if ($info === null) {
            return null;
        }
        $name = is_array($info) ? ($info['name'] ?? null) : null;
        $version = is_array($info) ? ($info['version'] ?? null) : null;
        if (!is_string($name) || !is_string($version) || !preg_match('/^[A-Za-z0-9._@\/-]{1,64}$/', $name) || !preg_match('/^[A-Za-z0-9.+_-]{1,32}$/', $version)) {
            throw new \InvalidArgumentException('KetaPay: appInfo must be [\'name\' => ..., \'version\' => ...] using letters, digits, dots and dashes');
        }

        return "$name/$version";
    }

    /**
     * @param string|Config $apiKeyOrConfig
     * @param Options|null $options
     * @return Config
     */
    private static function resolveConfig(string|array $apiKeyOrConfig, ?array $options): array
    {
        if (!is_string($apiKeyOrConfig)) {
            if ($options !== null) {
                throw new \InvalidArgumentException('KetaPay: pass options inside the config array, or use new KetaPay($key, $options)');
            }

            return $apiKeyOrConfig;
        }
        if ($options !== null && array_key_exists('apiKey', $options)) {
            throw new \InvalidArgumentException('KetaPay: the key is given twice; pass it as the first argument only');
        }

        return ['apiKey' => $apiKeyOrConfig] + ($options ?? []);
    }

    private static function isSafeBaseUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);

        return $scheme === 'https' || ($scheme === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true));
    }
}
