<?php

declare(strict_types=1);

namespace KetaPay\Resources\TestHelpers;

use KetaPay\Models\CheckoutSession;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class CheckoutSessions extends SandboxResource
{
    /**
     * Pays the session as if the buyer did.
     *
     * @param array{method?: 'card'|'bank_transfer'} $params
     * @param RequestOptions                         $options
     */
    public function pay(string $id, array $params = [], array $options = []): CheckoutSession
    {
        $this->sandboxOnly();

        return $this->object(CheckoutSession::class, 'POST', '/test_helpers/checkout_sessions/' . self::seg($id) . '/pay', $params, $options);
    }
}
