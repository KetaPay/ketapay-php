<?php

declare(strict_types=1);

namespace KetaPay\Http;

/**
 * Sends requests with PHP's built-in HTTP stream wrapper, for hosts without ext-curl. Needs
 * ext-openssl for HTTPS. A cancellation token is checked before sending, not mid-transfer.
 */
final class StreamTransport implements Transport
{
    public function send(Request $request): Response
    {
        if ($request->cancel?->isCancelled()) {
            throw new TransportException('Request was cancelled', cancelled: true);
        }
        $lines = [];
        foreach ($request->headers as $name => $value) {
            $lines[] = "$name: $value";
        }
        $lines[] = 'Connection: close';
        $context = stream_context_create([
            'http' => [
                'method' => $request->method,
                'header' => implode("\r\n", $lines),
                'content' => $request->body ?? '',
                'timeout' => $request->timeoutMs / 1000,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
        ]);

        $started = microtime(true);
        $error = null;
        set_error_handler(static function (int $_, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $stream = fopen($request->url, 'rb', false, $context);
            $body = $stream === false ? false : stream_get_contents($stream);
            $meta = $stream === false ? null : stream_get_meta_data($stream);
            $raw = $stream === false ? [] : ($meta['wrapper_data'] ?? []);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            restore_error_handler();
        }

        $timedOut = ($meta['timed_out'] ?? false) === true
            || (microtime(true) - $started) * 1000 >= $request->timeoutMs;
        if ($body === false || !is_array($raw) || $raw === [] || ($meta['timed_out'] ?? false) === true) {
            throw new TransportException($error ?? 'Could not reach KetaPay', timedOut: $timedOut);
        }

        return self::parse($raw, $body);
    }

    /**
     * @param array<mixed> $raw the response's status line and header lines
     */
    private static function parse(array $raw, string $body): Response
    {
        $status = 0;
        $headers = [];
        foreach ($raw as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                // A new status line (after a 100 Continue) starts a new set of headers.
                $status = (int) $m[1];
                $headers = [];
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return new Response($status, $headers, $body);
    }
}
