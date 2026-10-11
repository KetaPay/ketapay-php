<?php

declare(strict_types=1);

namespace KetaPay\Http;

/**
 * Sends one HTTP request. The SDK ships CurlTransport (the default), StreamTransport (used when
 * ext-curl is missing) and Psr18Transport (your own PSR-18 client). Write your own to send through
 * something else, for example `wp_remote_request` in WordPress.
 *
 * Return every response, whatever its status. Throw TransportException only when no response came
 * back (network failure, timeout or cancellation); retries and error mapping happen in the SDK.
 */
interface Transport
{
    /**
     * @throws TransportException
     */
    public function send(Request $request): Response;
}
