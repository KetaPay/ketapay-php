<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\Escrow;
use KetaPay\Models\ReleaseCode;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class Milestones extends AbstractResource
{
    /**
     * The seller marks the milestone delivered. Needs the seller's customer token.
     *
     * @param array<string, mixed>   $params  note, attachments
     * @param CustomerRequestOptions $options
     */
    public function deliver(string $escrowId, string $milestoneId, array $params, array $options): Escrow
    {
        return $this->object(Escrow::class, 'POST', self::path($escrowId, $milestoneId) . '/deliver', $params, self::customer($options));
    }

    /**
     * Pays the seller for this milestone. Send the buyer's customer token, or (for `release_code`
     * escrows) the buyer's `release_code` in `$params`.
     *
     * @param array<string, mixed> $params
     * @param RequestOptions       $options
     */
    public function release(string $escrowId, string $milestoneId, array $params = [], array $options = []): Escrow
    {
        return $this->object(Escrow::class, 'POST', self::path($escrowId, $milestoneId) . '/release', $params, $options);
    }

    /**
     * The buyer's release code, for the buyer's screen only. Needs the buyer's customer token.
     *
     * @param CustomerRequestOptions $options
     */
    public function releaseCode(string $escrowId, string $milestoneId, array $options): ReleaseCode
    {
        return $this->object(ReleaseCode::class, 'GET', self::path($escrowId, $milestoneId) . '/release_code', options: self::customer($options));
    }

    private static function path(string $escrowId, string $milestoneId): string
    {
        return '/escrows/' . self::seg($escrowId) . '/milestones/' . self::seg($milestoneId);
    }
}
