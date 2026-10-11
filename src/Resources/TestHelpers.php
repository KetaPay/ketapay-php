<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Http\HttpClient;

/** Sandbox only. With a live key every call fails at once, without contacting KetaPay. */
final class TestHelpers
{
    public readonly TestHelpers\CheckoutSessions $checkoutSessions;
    public readonly TestHelpers\Escrows $escrows;

    public function __construct(HttpClient $http)
    {
        $this->checkoutSessions = new TestHelpers\CheckoutSessions($http);
        $this->escrows = new TestHelpers\Escrows($http);
    }
}
