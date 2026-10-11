<?php

declare(strict_types=1);

namespace KetaPay\Http;

/** The default transport. Reuses one connection across requests and can cancel mid-transfer. */
final class CurlTransport implements Transport
{
    private ?\CurlHandle $handle = null;

    public function send(Request $request): Response
    {
        if ($request->url === '' || $request->method === '') {
            throw new \InvalidArgumentException('KetaPay: a request needs a method and a URL');
        }
        if ($request->cancel?->isCancelled()) {
            throw new TransportException('Request was cancelled', cancelled: true);
        }
        $this->handle ??= curl_init();
        $handle = $this->handle;
        curl_reset($handle);

        $headers = [];
        $lines = [];
        foreach ($request->headers as $name => $value) {
            $lines[] = "$name: $value";
        }
        // An empty Expect stops curl waiting for "100 Continue" on large uploads.
        $lines[] = 'Expect:';

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $request->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min($request->timeoutMs, 30_000),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($_, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ];
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }
        if ($request->cancel !== null) {
            $cancel = $request->cancel;
            $options[CURLOPT_NOPROGRESS] = false;
            // Returning non-zero aborts the transfer. XFERINFO is PHP 8.2+; PROGRESS does the same on 8.1.
            $options[defined('CURLOPT_XFERINFOFUNCTION') ? CURLOPT_XFERINFOFUNCTION : CURLOPT_PROGRESSFUNCTION] = static fn(): int => $cancel->isCancelled() ? 1 : 0;
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        if ($body === false || !is_string($body)) {
            $errno = curl_errno($handle);
            $message = curl_error($handle);
            // The handle may be in a bad state after an error; start fresh next time.
            $this->handle = null;
            throw new TransportException(
                $message !== '' ? $message : 'curl request failed',
                timedOut: $errno === CURLE_OPERATION_TIMEDOUT,
                cancelled: $errno === CURLE_ABORTED_BY_CALLBACK,
            );
        }

        return new Response((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $headers, $body);
    }
}
