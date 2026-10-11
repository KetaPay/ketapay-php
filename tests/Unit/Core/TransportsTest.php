<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\CancellationToken;
use KetaPay\Errors\ConnectionError;
use KetaPay\Errors\NotFoundError;
use KetaPay\Errors\RequestAbortedError;
use KetaPay\Http\CurlTransport;
use KetaPay\Http\Psr18Transport;
use KetaPay\Http\StreamTransport;
use KetaPay\Http\Transport;
use KetaPay\KetaPay;
use KetaPay\KetaPayObject;
use KetaPay\Tests\Support\LocalServer;
use KetaPay\Tests\Support\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Every built-in transport, over a real socket to a local `php -S` server. */
final class TransportsTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): Transport}> */
    public static function transports(): iterable
    {
        yield 'curl' => [static fn(): Transport => new CurlTransport()];
        yield 'streams' => [static fn(): Transport => new StreamTransport()];
        yield 'psr-18' => [static function (): Transport {
            $factory = new Psr17Factory();

            return new Psr18Transport(new SocketClient(), $factory, $factory);
        }];
    }

    /** @param \Closure(): Transport $transport */
    #[DataProvider('transports')]
    #[TestDox('sends method, headers, query and JSON body, and reads the response ($_dataName)')]
    public function testRoundTrip(\Closure $transport): void
    {
        $k = self::local($transport(), '/echo');
        $created = $k->checkoutSessions->create(['amount' => 100, 'title' => 'Café'], ['idempotencyKey' => 'k1', 'headers' => ['X-Trace' => 'abc']]);
        self::assertSame('req_local', $created->getLastResponse()?->requestId);
        $echo = self::echoed($created);
        self::assertSame('POST', $echo['method']);
        self::assertSame('/echo/checkout_sessions', $echo['path']);
        self::assertSame('Bearer sk_test_local', $echo['headers']['authorization'] ?? null);
        self::assertSame('k1', $echo['headers']['idempotency-key'] ?? null);
        self::assertSame('abc', $echo['headers']['x-trace'] ?? null);
        self::assertSame('application/json', $echo['headers']['content-type'] ?? null);
        self::assertSame(['amount' => 100, 'title' => 'Café'], json_decode($echo['body'], true));

        self::assertSame('status=active&limit=2', self::echoed($k->escrows->list(['status' => 'active', 'limit' => 2]))['query']);
    }

    /** @param \Closure(): Transport $transport */
    #[DataProvider('transports')]
    #[TestDox('uploads multipart bytes intact ($_dataName)')]
    public function testUpload(\Closure $transport): void
    {
        $bytes = random_bytes(4096);
        $echo = self::echoed(self::local($transport(), '/echo')->files->create(['file' => $bytes, 'filename' => 'blob "1".bin', 'purpose' => 'agreement', 'contentType' => 'image/png']));
        self::assertStringStartsWith('multipart/form-data; boundary=ketapay-', $echo['headers']['content-type'] ?? '');
        self::assertSame('agreement', $echo['form']['purpose'] ?? null);
        $file = $echo['files']['file'] ?? null;
        self::assertNotNull($file);
        self::assertSame('blob %221%22.bin', $file['name']);
        self::assertSame('image/png', $file['type']);
        self::assertSame($bytes, base64_decode($file['base64'], true));
    }

    /** @param \Closure(): Transport $transport */
    #[DataProvider('transports')]
    #[TestDox('turns error statuses into errors, with the request id ($_dataName)')]
    public function testErrorStatus(\Closure $transport): void
    {
        $e = self::catching(NotFoundError::class, fn() => self::local($transport(), '/missing')->escrows->retrieve('e1'));
        self::assertSame('resource_not_found', $e->code);
        self::assertSame('req_local', $e->requestId);
    }

    /** @param \Closure(): Transport $transport */
    #[DataProvider('transports')]
    #[TestDox('reports a refused connection as a network error ($_dataName)')]
    public function testRefused(\Closure $transport): void
    {
        $k = new KetaPay(['apiKey' => 'sk_test_local', 'baseUrl' => 'http://127.0.0.1:1', 'transport' => $transport(), 'maxRetries' => 0, 'logLevel' => 'off']);
        $e = self::catching(ConnectionError::class, fn() => $k->escrows->retrieve('e1'));
        self::assertSame('network_error', $e->code);
    }

    /** @param \Closure(): Transport $transport */
    #[DataProvider('transports')]
    #[TestDox('checks a cancellation token before sending ($_dataName)')]
    public function testCancelledBeforeSend(\Closure $transport): void
    {
        $token = new CancellationToken();
        $token->cancel();
        $request = new \KetaPay\Http\Request('GET', LocalServer::url() . '/echo/x', [], null, 1000, $token);
        $e = self::catching(\KetaPay\Http\TransportException::class, fn() => $transport()->send($request));
        self::assertTrue($e->cancelled);
        $k = self::local($transport(), '/echo');
        self::catching(RequestAbortedError::class, fn() => $k->escrows->retrieve('e1', ['cancel' => $token]));
    }

    #[TestDox('curl reuses its connection across requests')]
    public function testCurlReuse(): void
    {
        $k = self::local(new CurlTransport(), '/echo');
        self::assertSame('GET', self::echoed($k->escrows->retrieve('a'))['method']);
        self::assertSame('GET', self::echoed($k->escrows->retrieve('b'))['method']);
        self::assertSame('DELETE', self::echoed($k->webhookEndpoints->del('c'))['method']);
    }

    /**
     * What the local server's /echo route saw.
     *
     * @return array{method: string, path: string, query: string, headers: array<string, string>, body: string, form: array<string, string>, files: array<string, array{name: string, type: string, base64: string}>}
     */
    private static function echoed(KetaPayObject $response): array
    {
        $echo = $response->toArray();
        self::assertSame('echo', $echo['object'] ?? null);
        foreach (['method', 'path', 'query', 'body'] as $key) {
            self::assertIsString($echo[$key] ?? null);
        }
        foreach (['headers', 'form', 'files'] as $key) {
            self::assertIsArray($echo[$key] ?? null);
        }

        /** @var array{method: string, path: string, query: string, headers: array<string, string>, body: string, form: array<string, string>, files: array<string, array{name: string, type: string, base64: string}>} $echo checked above */
        return $echo;
    }

    private static function local(Transport $transport, string $prefix): KetaPay
    {
        return new KetaPay(['apiKey' => 'sk_test_local', 'baseUrl' => LocalServer::url() . $prefix, 'transport' => $transport, 'maxRetries' => 0, 'logLevel' => 'off']);
    }
}

/** A minimal PSR-18 client over PHP streams, standing in for Guzzle or Symfony HttpClient. */
final class SocketClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $lines = [];
        foreach ($request->getHeaders() as $name => $values) {
            $lines[] = $name . ': ' . implode(', ', $values);
        }
        $context = stream_context_create(['http' => [
            'method' => $request->getMethod(),
            'header' => implode("\r\n", $lines),
            'content' => (string) $request->getBody(),
            'ignore_errors' => true,
        ]]);
        $http_response_header = [];
        $body = @file_get_contents((string) $request->getUri(), false, $context);
        if ($body === false) {
            throw new class ('Connection refused', $request) extends \RuntimeException implements NetworkExceptionInterface {
                public function __construct(string $message, private RequestInterface $request)
                {
                    parent::__construct($message);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }
        // PHP 8.4 added http_get_last_response_headers() and 8.5 deprecated the magic variable it replaces.
        $raw = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;
        $status = 0;
        $headers = [];
        foreach (is_array($raw) ? $raw : [] as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return new PsrResponse($status, $headers, $body);
    }
}
