<?php

declare(strict_types=1);

namespace KetaPay\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests through any PSR-18 client (Guzzle, Symfony HttpClient, Laravel's Guzzle instance).
 * Needs `psr/http-client` and `psr/http-factory`, which your framework most likely has already.
 *
 * PSR-18 has no per-request timeout or cancellation: configure the timeout on your client, and a
 * cancellation token is checked before each attempt only.
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function send(Request $request): Response
    {
        if ($request->cancel?->isCancelled()) {
            throw new TransportException('Request was cancelled', cancelled: true);
        }
        $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $response = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), timedOut: stripos($e->getMessage(), 'timed out') !== false, previous: $e);
        }

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        return new Response($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
