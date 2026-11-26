<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Collection;
use KetaPay\Models\Dispute;
use KetaPay\Models\Evidence;
use KetaPay\Models\MoneyAction;

/**
 * @phpstan-import-type CreateDisputeRequest from \KetaPay\Types\Params
 * @phpstan-import-type ResolveDisputeRequest from \KetaPay\Types\Params
 * @phpstan-import-type SubmitEvidenceRequest from \KetaPay\Types\Params
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class Disputes extends AbstractResource
{
    /**
     * Opens a dispute for the buyer or the seller whose customer token you send.
     *
     * @param CreateDisputeRequest   $params  reason, description, milestone_id, files
     * @param CustomerRequestOptions $options
     */
    public function create(string $escrowId, array $params, array $options): Dispute
    {
        return $this->object(Dispute::class, 'POST', '/escrows/' . self::seg($escrowId) . '/disputes', $params, self::customer($options));
    }

    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): Dispute
    {
        return $this->object(Dispute::class, 'GET', '/disputes/' . self::seg($id), options: $options);
    }

    /**
     * Every dispute on an escrow, newest first.
     *
     * @param RequestOptions $options
     *
     * @return Collection<Dispute>
     */
    public function listForEscrow(string $escrowId, array $options = []): Collection
    {
        return $this->collection(Dispute::class, '/escrows/' . self::seg($escrowId) . '/disputes', $options);
    }

    /**
     * Settles against the customer whose token you send: the buyer releases, the seller refunds.
     *
     * @param CustomerRequestOptions $options
     */
    public function concede(string $id, array $options): Dispute
    {
        return $this->object(Dispute::class, 'POST', '/disputes/' . self::seg($id) . '/concede', options: self::customer($options));
    }

    /**
     * Hands an open dispute to whoever decides it (KetaPay, or your platform), for the customer whose token you send. No money moves.
     *
     * @param CustomerRequestOptions $options
     */
    public function escalate(string $id, array $options): Dispute
    {
        return $this->object(Dispute::class, 'POST', '/disputes/' . self::seg($id) . '/escalate', options: self::customer($options));
    }

    /**
     * A platform's ruling on a dispute between two of its customers, with your key alone: release
     * it to the seller, refund the buyer, or split it (`refund_amount` in kobo back to the buyer,
     * the rest to the seller). Needs `escrows:manage`.
     *
     * @param ResolveDisputeRequest $params  outcome, refund_amount (split only), reason
     * @param RequestOptions        $options
     */
    public function resolve(string $id, array $params, array $options = []): MoneyAction
    {
        return $this->object(MoneyAction::class, 'POST', '/disputes/' . self::seg($id) . '/resolve', $params, $options);
    }

    /**
     * Withdraws a dispute. Only whoever opened it.
     *
     * @param CustomerRequestOptions $options
     */
    public function withdraw(string $id, array $options): Dispute
    {
        return $this->object(Dispute::class, 'POST', '/disputes/' . self::seg($id) . '/withdraw', options: self::customer($options));
    }

    /**
     * @param SubmitEvidenceRequest   $params  statement, files
     * @param CustomerRequestOptions $options
     */
    public function submitEvidence(string $id, array $params, array $options): Evidence
    {
        return $this->object(Evidence::class, 'POST', '/disputes/' . self::seg($id) . '/evidence', $params, self::customer($options));
    }

    /**
     * Both sides' evidence: with a party's customer token, or your key alone on a dispute your
     * platform decides.
     *
     * @param CustomerRequestOptions|RequestOptions $options
     *
     * @return Collection<Evidence>
     */
    public function listEvidence(string $id, array $options = []): Collection
    {
        return $this->collection(Evidence::class, '/disputes/' . self::seg($id) . '/evidence', $options);
    }
}
