<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Collection;
use KetaPay\Models\Chargeback;
use KetaPay\Models\Evidence;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class Chargebacks extends AbstractResource
{
    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): Chargeback
    {
        return $this->object(Chargeback::class, 'GET', '/chargebacks/' . self::seg($id), options: $options);
    }

    /**
     * @param RequestOptions $options
     *
     * @return Collection<Chargeback>
     */
    public function listForEscrow(string $escrowId, array $options = []): Collection
    {
        return $this->collection(Chargeback::class, '/escrows/' . self::seg($escrowId) . '/chargebacks', $options);
    }

    /**
     * The seller adds proof of delivery. Needs the seller's customer token.
     *
     * @param array<string, mixed>   $params  statement, files
     * @param CustomerRequestOptions $options
     */
    public function submitEvidence(string $id, array $params, array $options): Evidence
    {
        return $this->object(Evidence::class, 'POST', '/chargebacks/' . self::seg($id) . '/evidence', $params, self::customer($options));
    }
}
