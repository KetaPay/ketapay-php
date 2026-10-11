<?php

declare(strict_types=1);

namespace KetaPay\Http;

use KetaPay\ApiResponse;
use KetaPay\CancellationToken;
use KetaPay\Errors\APIError;
use KetaPay\Errors\ConnectionError;
use KetaPay\Errors\ErrorFactory;
use KetaPay\Errors\RequestAbortedError;
use KetaPay\KetaPay;

/**
 * Auth, idempotency, retries, timeouts, cancellation and logging for every request. Internal.
 *
 * @internal
 *
 * @phpstan-type RequestOptions array{idempotencyKey?: string, customerToken?: string, timeoutMs?: int, maxRetries?: int, cancel?: CancellationToken, headers?: array<string, string>}
 */
final class HttpClient
{
    public const DEFAULT_BASE_URL = 'https://api.ketapay.com/v1';

    private const OPTION_KEYS = ['idempotencyKey', 'customerToken', 'timeoutMs', 'maxRetries', 'cancel', 'headers'];

    /** Headers the SDK owns. Default and per-call headers can never set these. */
    private const RESERVED_HEADERS = ['authorization', 'accept', 'user-agent', 'idempotency-key', 'ketapay-customer-token', 'content-type'];

    public readonly bool $livemode;

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $maxRetries,
        private readonly int $timeoutMs,
        private readonly Transport $transport,
        private readonly array $defaultHeaders,
        private readonly Logger $log,
        /** Appended to the User-Agent, for libraries built on this one: "ketapay-laravel/0.1.0". */
        private readonly ?string $appInfo = null,
    ) {
        $this->livemode = str_starts_with($apiKey, 'sk_live_');
    }

    /**
     * @param 'GET'|'POST'|'DELETE'                                                   $method
     * @param array<string, scalar|null>|null                                         $query
     * @param array<string, mixed>|null                                               $body  sent as JSON
     * @param array{body: string, contentType: string}|null                           $multipart
     * @param array<string, mixed>                                                    $options
     *
     * @return array{0: \stdClass, 1: ApiResponse}
     */
    public function request(string $method, string $path, ?array $query = null, ?array $body = null, ?array $multipart = null, array $options = []): array
    {
        $options = self::normalize($options);
        $url = $this->baseUrl . $path . self::queryString($query);

        $headers = self::safeHeaders($this->defaultHeaders);
        foreach (self::safeHeaders($options['headers'] ?? []) as $name => $value) {
            $headers[$name] = $value;
        }
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        $headers['Accept'] = 'application/json';
        $headers['User-Agent'] = 'ketapay-php/' . KetaPay::VERSION . ' php/' . PHP_VERSION . ($this->appInfo !== null ? ' ' . $this->appInfo : '');
        if ($method === 'POST') {
            $headers['Idempotency-Key'] = $options['idempotencyKey'] ?? self::uuid();
        }
        if (isset($options['customerToken'])) {
            $headers['KetaPay-Customer-Token'] = $options['customerToken'];
        }
        $payload = null;
        if ($multipart !== null) {
            $headers['Content-Type'] = $multipart['contentType'];
            $payload = $multipart['body'];
        } elseif ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        }

        $cancel = $options['cancel'] ?? null;
        $maxRetries = $options['maxRetries'] ?? $this->maxRetries;
        $timeoutMs = $options['timeoutMs'] ?? $this->timeoutMs;
        for ($attempt = 0; ; $attempt++) {
            if ($cancel?->isCancelled()) {
                throw self::aborted();
            }
            $started = microtime(true);
            $this->log->debug('request', ['method' => $method, 'path' => $path, 'attempt' => $attempt]);
            try {
                $response = $this->transport->send(new Request($method, $url, $headers, $payload, $timeoutMs, $cancel));
            } catch (TransportException $e) {
                if ($e->cancelled || $cancel?->isCancelled()) {
                    throw self::aborted($e);
                }
                if ($attempt < $maxRetries) {
                    $wait = self::backoffMs($attempt);
                    $this->log->warn('retrying after a network error', ['method' => $method, 'path' => $path, 'attempt' => $attempt, 'waitMs' => $wait, 'timedOut' => $e->timedOut]);
                    self::sleep($wait, $cancel);
                    continue;
                }
                throw new ConnectionError(
                    $e->timedOut ? "Request timed out after {$timeoutMs}ms" : 'Could not reach KetaPay',
                    'connection_error',
                    $e->timedOut ? 'timeout' : 'network_error',
                    previous: $e,
                );
            }

            $requestId = $response->header('KetaPay-Request-Id');
            $this->log->debug('response', [
                'method' => $method,
                'path' => $path,
                'status' => $response->statusCode,
                'requestId' => $requestId,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $ok = $response->statusCode >= 200 && $response->statusCode < 300;
            $data = self::decode($response, $ok, $requestId);
            if ($ok) {
                return [$data ?? new \stdClass(), new ApiResponse($response->statusCode, $response->headers, $response->body, $requestId)];
            }

            $retryable = $response->statusCode === 429 || $response->statusCode >= 500;
            if ($retryable && $attempt < $maxRetries) {
                $wait = self::retryDelayMs($response, $attempt);
                $this->log->warn('retrying', ['method' => $method, 'path' => $path, 'status' => $response->statusCode, 'requestId' => $requestId, 'attempt' => $attempt, 'waitMs' => $wait]);
                self::sleep($wait, $cancel);
                continue;
            }
            throw ErrorFactory::fromResponse($response->statusCode, $data, $requestId);
        }
    }

    /**
     * Checks request options at runtime: most PHP callers run no static analysis, and a typo such as
     * `customer_token` must fail loudly instead of sending the request without the customer's proof.
     *
     * @param array<string, mixed> $options
     *
     * @return RequestOptions
     */
    private static function normalize(array $options): array
    {
        $unknown = array_diff(array_keys($options), self::OPTION_KEYS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('KetaPay: unknown request option ' . implode(', ', $unknown) . '. Use ' . implode(', ', self::OPTION_KEYS) . '.');
        }
        $out = [];
        foreach (['idempotencyKey', 'customerToken'] as $key) {
            if (isset($options[$key])) {
                if (!is_string($options[$key]) || $options[$key] === '') {
                    throw new \InvalidArgumentException("KetaPay: $key must be a non-empty string");
                }
                $out[$key] = $options[$key];
            }
        }
        foreach (['timeoutMs', 'maxRetries'] as $key) {
            if (isset($options[$key])) {
                if (!is_int($options[$key]) || $options[$key] < 0) {
                    throw new \InvalidArgumentException("KetaPay: $key must be a whole number, 0 or more");
                }
                $out[$key] = $options[$key];
            }
        }
        if (isset($options['cancel'])) {
            if (!$options['cancel'] instanceof CancellationToken) {
                throw new \InvalidArgumentException('KetaPay: the cancel option must be a KetaPay\\CancellationToken');
            }
            $out['cancel'] = $options['cancel'];
        }
        if (isset($options['headers'])) {
            if (!is_array($options['headers'])) {
                throw new \InvalidArgumentException('KetaPay: headers must be an array of name => value');
            }
            $out['headers'] = self::safeHeaders($options['headers']);
        }

        return $out;
    }

    /**
     * @param array<mixed> $headers
     *
     * @return array<string, string>
     */
    private static function safeHeaders(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            $name = (string) $name;
            if (!in_array(strtolower($name), self::RESERVED_HEADERS, true) && is_scalar($value)) {
                $safe[$name] = (string) $value;
            }
        }

        return $safe;
    }

    /**
     * @param array<string, scalar|null>|null $query
     */
    private static function queryString(?array $query): string
    {
        $params = [];
        foreach ($query ?? [] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $params[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    private static function decode(Response $response, bool $ok, ?string $requestId): ?\stdClass
    {
        if ($response->body === '') {
            return null;
        }
        try {
            $data = json_decode($response->body, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            if ($ok) {
                throw new APIError('KetaPay returned a response that is not JSON', 'api_error', 'invalid_response', null, $response->statusCode, $requestId, $e);
            }

            return null;
        }

        return $data instanceof \stdClass ? $data : null;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function backoffMs(int $attempt): int
    {
        $base = min(500 * 2 ** $attempt, 8_000);

        return (int) ($base / 2 + mt_rand() / mt_getrandmax() * ($base / 2));
    }

    private static function retryDelayMs(Response $response, int $attempt): int
    {
        $reset = $response->header('X-RateLimit-Reset');
        if ($response->statusCode === 429 && $reset !== null && is_numeric($reset) && (float) $reset > 0) {
            $wait = (int) ceil((float) $reset * 1000 - microtime(true) * 1000);
            if ($wait > 0) {
                return min($wait, 10_000);
            }
        }

        return self::backoffMs($attempt);
    }

    /** Sleeps in short slices so a cancellation lands during the wait, not after it. */
    private static function sleep(int $ms, ?CancellationToken $cancel): void
    {
        $until = microtime(true) + $ms / 1000;
        while (($left = $until - microtime(true)) > 0) {
            if ($cancel?->isCancelled()) {
                throw self::aborted();
            }
            usleep((int) (min($left, 0.02) * 1_000_000));
        }
        if ($cancel?->isCancelled()) {
            throw self::aborted();
        }
    }

    private static function aborted(?\Throwable $previous = null): RequestAbortedError
    {
        return new RequestAbortedError('Request was cancelled', 'aborted', 'aborted', previous: $previous);
    }
}
