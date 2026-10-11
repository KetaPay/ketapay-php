<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Webhooks;

use KetaPay\Errors\WebhookSignatureError;
use KetaPay\KetaPay;
use KetaPay\Models\Escrow;
use KetaPay\Models\Event;
use KetaPay\Tests\Support\TestCase;
use KetaPay\Webhook;
use PHPUnit\Framework\Attributes\TestDox;

final class SignatureTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';
    private const NOW = 1_791_640_000;
    private const BODY = '{"id":"evt_1","object":"event","type":"escrow.funded","data":{"object":{"id":"e1","object":"escrow"}}}';

    private static function sign(string $payload, string $key, int $t = self::NOW): string
    {
        return self::hmac("$t.$payload", $key);
    }

    /** HMAC-SHA256 built by hand from RFC 2104, not hash_hmac(), to prove verify() matches a reference. */
    private static function hmac(string $message, string $key): string
    {
        $block = 64;
        $key = strlen($key) > $block ? hash('sha256', $key, true) : $key;
        $key = str_pad($key, $block, "\0");

        return hash('sha256', ($key ^ str_repeat("\x5c", $block)) . hash('sha256', ($key ^ str_repeat("\x36", $block)) . $message, true));
    }

    private static function code(callable $fn): string
    {
        return self::catching(WebhookSignatureError::class, $fn)->code;
    }

    #[TestDox('[WH-001] returns the parsed event for a valid signature, from a string or raw bytes')]
    public function testValid(): void
    {
        $header = 't=' . self::NOW . ',v1=' . self::sign(self::BODY, self::SECRET);
        $event = Webhook::verify(self::BODY, $header, self::SECRET, now: self::NOW);
        self::assertInstanceOf(Event::class, $event);
        self::assertSame('evt_1', $event->id);
        self::assertInstanceOf(Escrow::class, $event->data->object);
        $bytes = self::BODY . "\n";
        $event = Webhook::verify($bytes, 't=' . self::NOW . ',v1=' . self::sign($bytes, self::SECRET), self::SECRET, now: self::NOW);
        self::assertSame('escrow.funded', $event->type);
    }

    #[TestDox('[WH-002] [WH-003] rejects a tampered body or the wrong secret')]
    public function testMismatch(): void
    {
        $header = 't=' . self::NOW . ',v1=' . self::sign(self::BODY, self::SECRET);
        self::assertSame('signature_mismatch', self::code(fn() => Webhook::verify(str_replace('e1', 'e2', self::BODY), $header, self::SECRET, now: self::NOW)));
        $other = 't=' . self::NOW . ',v1=' . self::sign(self::BODY, 'other');
        self::assertSame('signature_mismatch', self::code(fn() => Webhook::verify(self::BODY, $other, self::SECRET, now: self::NOW)));
    }

    #[TestDox('[WH-004] rejects a timestamp older than 5 minutes, and honours a custom tolerance')]
    public function testOld(): void
    {
        $old = self::NOW - 301;
        $header = "t=$old,v1=" . self::sign(self::BODY, self::SECRET, $old);
        self::assertSame('timestamp_outside_tolerance', self::code(fn() => Webhook::verify(self::BODY, $header, self::SECRET, now: self::NOW)));
        self::assertSame('evt_1', Webhook::verify(self::BODY, $header, self::SECRET, 600, self::NOW)->id);
    }

    #[TestDox('[WH-005] rejects a timestamp more than 5 minutes in the future')]
    public function testFuture(): void
    {
        $future = self::NOW + 301;
        $header = "t=$future,v1=" . self::sign(self::BODY, self::SECRET, $future);
        self::assertSame('timestamp_outside_tolerance', self::code(fn() => Webhook::verify(self::BODY, $header, self::SECRET, now: self::NOW)));
    }

    #[TestDox('[WH-006] rejects a missing, malformed or non-hex header, each with its own code')]
    public function testMalformed(): void
    {
        self::assertSame('missing_signature', self::code(fn() => Webhook::verify(self::BODY, null, self::SECRET, now: self::NOW)));
        self::assertSame('missing_signature', self::code(fn() => Webhook::verify(self::BODY, '', self::SECRET, now: self::NOW)));
        self::assertSame('malformed_signature', self::code(fn() => Webhook::verify(self::BODY, 'v1=abc', self::SECRET, now: self::NOW)));
        self::assertSame('malformed_signature', self::code(fn() => Webhook::verify(self::BODY, 't=' . self::NOW, self::SECRET, now: self::NOW)));
        self::assertSame('malformed_signature', self::code(fn() => Webhook::verify(self::BODY, 't=soon,v1=abc', self::SECRET, now: self::NOW)));
        self::assertSame('signature_mismatch', self::code(fn() => Webhook::verify(self::BODY, 't=' . self::NOW . ',v1=zz', self::SECRET, now: self::NOW)));
    }

    #[TestDox('[WH-007] accepts either signature while a secret rotates, and several secrets on our side')]
    public function testRotation(): void
    {
        $both = 't=' . self::NOW . ',v1=' . self::sign(self::BODY, 'whsec_new') . ',v1=' . self::sign(self::BODY, 'whsec_old');
        self::assertSame('evt_1', Webhook::verify(self::BODY, $both, 'whsec_old', now: self::NOW)->id);
        $oldOnly = 't=' . self::NOW . ',v1=' . self::sign(self::BODY, 'whsec_old');
        self::assertSame('evt_1', Webhook::verify(self::BODY, $oldOnly, ['whsec_new', 'whsec_old'], now: self::NOW)->id);
        self::catching(\InvalidArgumentException::class, fn() => Webhook::verify(self::BODY, $oldOnly, [], now: self::NOW));
        self::catching(\InvalidArgumentException::class, fn() => Webhook::verify(self::BODY, $oldOnly, '', now: self::NOW));
    }

    #[TestDox('[WH-008] rejects a correctly signed body that is not JSON')]
    public function testNotJson(): void
    {
        foreach (['not json', '"a string"', '[1,2]'] as $text) {
            $header = 't=' . self::NOW . ',v1=' . self::sign($text, self::SECRET);
            self::assertSame('invalid_payload', self::code(fn() => Webhook::verify($text, $header, self::SECRET, now: self::NOW)), $text);
        }
    }

    #[TestDox('[WH-009] round-trips with generateTestSignatureHeader and is reachable from the client')]
    public function testHelper(): void
    {
        $k = new KetaPay('sk_test_x');
        $header = $k->webhooks->generateTestSignatureHeader(self::BODY, self::SECRET);
        self::assertSame('evt_1', $k->webhooks->verify(self::BODY, $header, self::SECRET)->id);
        self::assertSame('evt_1', Webhook::verify(self::BODY, Webhook::generateTestSignatureHeader(self::BODY, self::SECRET), self::SECRET)->id);
        self::assertSame('t=' . self::NOW . ',v1=' . self::sign(self::BODY, self::SECRET), Webhook::generateTestSignatureHeader(self::BODY, self::SECRET, self::NOW));
    }
}
