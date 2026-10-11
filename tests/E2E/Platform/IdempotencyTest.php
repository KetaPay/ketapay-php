<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Platform;

use KetaPay\Errors\IdempotencyError;
use KetaPay\Tests\E2E\Support\E2ETestCase;
use KetaPay\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class IdempotencyTest extends E2ETestCase
{
    #[TestDox('[E2E-015] replays the same key and body, and refuses the key with a different body')]
    public function testIdempotency(): void
    {
        $k = self::ketapay();
        $params = self::sessionParams();
        $key = Env::runId() . '-idem-' . bin2hex(random_bytes(4));
        $a = $k->checkoutSessions->create($params, ['idempotencyKey' => $key]);
        self::assertSame($a->id, $k->checkoutSessions->create($params, ['idempotencyKey' => $key])->id);
        $e = self::catching(IdempotencyError::class, fn() => $k->checkoutSessions->create([...$params, 'title' => 'Different'], ['idempotencyKey' => $key]));
        self::assertSame('idempotency_key_reused', $e->code);
    }
}
