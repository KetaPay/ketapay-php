<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Chargeback
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $escrow_id
 * @property-read list<string> $milestone_ids The milestones the reversed card payment covered
 * @property-read int $amount
 * @property-read string $reason The reason the card bank gave
 * @property-read string $status `needs_response`: KetaPay is gathering evidence (the seller can add some until `respond_by`). `under_review`: with the card bank. `won`: reversed in KetaPay's favour, nothing changes. `lost`: the bank took the money back.
 * @property-read string|null $respond_by
 * @property-read int $recovered_from_seller For `lost`, how much has been recovered from the seller's balance and payouts so far
 * @property-read int $commission_reversed For `lost` after release, your commission taken back from your commission balance
 * @property-read string $opened_at
 * @property-read string|null $closed_at
 */
final class Chargeback extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'chargeback'];
}
