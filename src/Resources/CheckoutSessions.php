<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\BankTransferInstructions;
use KetaPay\Models\CheckoutSession;
use KetaPay\Page;

/**
 * @phpstan-import-type CreateCheckoutSessionRequest from \KetaPay\Types\Params
 * @phpstan-import-type ListCheckoutSessionsParams from \KetaPay\Types\Params
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class CheckoutSessions extends AbstractResource
{
    /**
     * Starts checkout for one agreement. Call from your server only.
     *
     * @param CreateCheckoutSessionRequest $params
     * @param RequestOptions       $options
     */
    public function create(array $params, array $options = []): CheckoutSession
    {
        return $this->object(CheckoutSession::class, 'POST', '/checkout_sessions', $params, $options);
    }

    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): CheckoutSession
    {
        return $this->object(CheckoutSession::class, 'GET', '/checkout_sessions/' . self::seg($id), options: $options);
    }

    /**
     * @param ListCheckoutSessionsParams $params filters: status, external_reference, limit, cursor
     * @param RequestOptions             $options
     *
     * @return Page<CheckoutSession>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->page(CheckoutSession::class, '/checkout_sessions', $params, $options);
    }

    /**
     * Closes an unpaid session.
     *
     * @param RequestOptions $options
     */
    public function cancel(string $id, array $options = []): CheckoutSession
    {
        return $this->object(CheckoutSession::class, 'POST', '/checkout_sessions/' . self::seg($id) . '/cancel', options: $options);
    }

    /**
     * Bank transfer details for your own payment screen. Needs the buyer's customer token.
     *
     * @param CustomerRequestOptions $options
     */
    public function bankTransfer(string $id, array $options): BankTransferInstructions
    {
        return $this->object(BankTransferInstructions::class, 'POST', '/checkout_sessions/' . self::seg($id) . '/bank_transfer', options: self::customer($options));
    }
}
