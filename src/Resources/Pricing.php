<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\Pricing as PricingObject;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Pricing extends AbstractResource
{
    /**
     * The rate your next escrow is priced on: the fee, VAT, who pays it and the escrow sizes it covers.
     *
     * @param RequestOptions $options
     */
    public function retrieve(array $options = []): PricingObject
    {
        return $this->object(PricingObject::class, 'GET', '/pricing', null, $options);
    }
}
