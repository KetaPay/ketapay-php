<?php

declare(strict_types=1);

namespace KetaPay\Tests\Support;

use KetaPay\Http\Request;
use KetaPay\Http\Response;
use KetaPay\Http\Transport;
use KetaPay\Http\TransportException;

/** A transport that answers from a queue of replies and records every request. */
final class MockTransport implements Transport
{
    /** @var list<Request> */
    public array $requests = [];

    /** @var list<array{status?: int, body?: mixed, raw?: string, headers?: array<string, string>}|TransportException> */
    private array $replies;

    /** @var (\Closure(Request): void)|null */
    public ?\Closure $onSend = null;

    /**
     * @param array{status?: int, body?: mixed, raw?: string, headers?: array<string, string>}|TransportException ...$replies
     */
    public function __construct(array|TransportException ...$replies)
    {
        $this->replies = array_values($replies);
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        if ($this->onSend !== null) {
            ($this->onSend)($request);
        }
        $reply = array_shift($this->replies) ?? ['body' => new \stdClass()];
        if ($reply instanceof TransportException) {
            throw $reply;
        }
        $body = $reply['raw'] ?? (array_key_exists('body', $reply) ? json_encode($reply['body'], JSON_THROW_ON_ERROR) : '');
        $headers = ['content-type' => 'application/json'];
        foreach ($reply['headers'] ?? [] as $name => $value) {
            $headers[strtolower($name)] = $value;
        }

        return new Response($reply['status'] ?? 200, $headers, $body);
    }

    public function last(): Request
    {
        return $this->requests[array_key_last($this->requests) ?? throw new \LogicException('no requests sent')];
    }

    /** @return array<mixed> the JSON body of the last request */
    public function lastJson(): array
    {
        $decoded = json_decode((string) $this->last()->body, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
