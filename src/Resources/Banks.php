<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Collection;
use KetaPay\Models\Bank;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Banks extends AbstractResource
{
    /**
     * Every bank a payout can go to, for your payout account form.
     *
     * @param RequestOptions $options
     *
     * @return Collection<Bank>
     */
    public function list(array $options = []): Collection
    {
        return $this->collection(Bank::class, '/banks', $options);
    }
}
