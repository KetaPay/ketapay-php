<?php

declare(strict_types=1);

namespace KetaPay\Resources\TestHelpers;

use KetaPay\Models\CustomerVerification;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Customers extends SandboxResource
{
    /**
     * Verifies the customer whose token you send (`customerToken`), as finishing hosted
     * verification would. Their payouts held as `awaiting_verification` go on to their payout account.
     *
     * @param RequestOptions $options
     */
    public function verify(array $options = []): CustomerVerification
    {
        $this->sandboxOnly();

        return $this->object(CustomerVerification::class, 'POST', '/test_helpers/customers/verify', [], $options);
    }
}
