<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Collection;
use KetaPay\Models\Deleted;
use KetaPay\Models\PayoutAccount;

/**
 * A customer's bank accounts. Every call needs that customer's token.
 *
 * @phpstan-import-type CreatePayoutAccountRequest from \KetaPay\Types\Params
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class PayoutAccounts extends AbstractResource
{
    /**
     * Starts adding a bank account; KetaPay sends the customer a code naming it.
     *
     * @param CreatePayoutAccountRequest   $params  bank_code, account_number
     * @param CustomerRequestOptions $options
     */
    public function create(array $params, array $options): PayoutAccount
    {
        return $this->object(PayoutAccount::class, 'POST', '/payout_accounts', $params, self::customer($options));
    }

    /**
     * Activates the account with the code the customer received.
     *
     * @param CustomerRequestOptions $options
     */
    public function confirm(string $id, string $code, array $options): PayoutAccount
    {
        return $this->object(PayoutAccount::class, 'POST', '/payout_accounts/' . self::seg($id) . '/confirm', ['code' => $code], self::customer($options));
    }

    /**
     * @param CustomerRequestOptions $options
     *
     * @return Collection<PayoutAccount>
     */
    public function list(array $options): Collection
    {
        return $this->collection(PayoutAccount::class, '/payout_accounts', self::customer($options));
    }

    /** @param CustomerRequestOptions $options */
    public function del(string $id, array $options): Deleted
    {
        return $this->object(Deleted::class, 'DELETE', '/payout_accounts/' . self::seg($id), options: self::customer($options));
    }
}
