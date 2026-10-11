<?php

declare(strict_types=1);

namespace KetaPay\Resources\TestHelpers;

use KetaPay\Models\Escrow;

/**
 * @phpstan-import-type SimulateRequest from \KetaPay\Types\Params
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Escrows extends SandboxResource
{
    /**
     * Does what a buyer, seller, bank or KetaPay would do, with the same rules and webhooks.
     *
     * @param SimulateRequest $params action, milestone_id, ...
     * @param RequestOptions       $options
     */
    public function simulate(string $id, array $params, array $options = []): Escrow
    {
        $this->sandboxOnly();

        return $this->object(Escrow::class, 'POST', '/test_helpers/escrows/' . self::seg($id) . '/simulate', $params, $options);
    }
}
