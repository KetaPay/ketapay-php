<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\CustomerSession;
use KetaPay\Models\CustomerToken;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class CustomerSessions extends AbstractResource
{
    /**
     * Sends a one-time code to the customer's email or phone.
     *
     * @param array{email: string}|array{phone: string} $params exactly one of email and phone
     * @param RequestOptions                            $options
     */
    public function create(array $params, array $options = []): CustomerSession
    {
        return $this->object(CustomerSession::class, 'POST', '/customer_sessions', $params, $options);
    }

    /**
     * Exchanges the code for a customer token valid for 15 minutes. Keep it on your server.
     *
     * @param RequestOptions $options
     */
    public function verify(string $id, string $code, array $options = []): CustomerToken
    {
        return $this->object(CustomerToken::class, 'POST', '/customer_sessions/' . self::seg($id) . '/verify', ['code' => $code], $options);
    }
}
