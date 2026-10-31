<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Http\HttpClient;
use KetaPay\Models\Escrow;
use KetaPay\Models\Flag;
use KetaPay\Models\MoneyAction;
use KetaPay\Page;

/**
 * @phpstan-import-type CreateFlagRequest from \KetaPay\Types\Params
 * @phpstan-import-type CreateRefundRequest from \KetaPay\Types\Params
 * @phpstan-import-type ListEscrowsParams from \KetaPay\Types\Params
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Escrows extends AbstractResource
{
    public readonly Milestones $milestones;

    public function __construct(HttpClient $http)
    {
        parent::__construct($http);
        $this->milestones = new Milestones($http);
    }

    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): Escrow
    {
        return $this->object(Escrow::class, 'GET', '/escrows/' . self::seg($id), options: $options);
    }

    /**
     * @param ListEscrowsParams $params filters: status, external_reference, limit, cursor
     * @param RequestOptions             $options
     *
     * @return Page<Escrow>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->page(Escrow::class, '/escrows', $params, $options);
    }

    /**
     * Refunds your own sale with your key alone. Needs a key with the `escrows:manage` scope.
     *
     * @param CreateRefundRequest $params
     * @param RequestOptions      $options
     */
    public function refund(string $id, array $params, array $options = []): MoneyAction
    {
        return $this->object(MoneyAction::class, 'POST', '/escrows/' . self::seg($id) . '/refund', $params, $options);
    }

    /**
     * Asks KetaPay to review an escrow.
     *
     * @param CreateFlagRequest $params
     * @param RequestOptions       $options
     */
    public function flag(string $id, array $params, array $options = []): Flag
    {
        return $this->object(Flag::class, 'POST', '/escrows/' . self::seg($id) . '/flag', $params, $options);
    }
}
