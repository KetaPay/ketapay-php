<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Milestone
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read int $position Order in the plan, starting at 1
 * @property-read string $title
 * @property-read string|null $description
 * @property-read int $amount
 * @property-read string|null $due_date
 * @property-read string $status See \KetaPay\Enums\MilestoneStatus.
 * @property-read Delivery|null $delivery What the seller sent when marking it delivered. Null before that.
 * @property-read string|null $auto_release_at When it releases if the buyer does nothing. Null if the escrow never auto-releases or it isn't delivered yet.
 * @property-read int $seller_fee
 * @property-read int $commission Your share from this milestone's release
 * @property-read int $seller_payout
 * @property-read Payout|null $payout Where the seller's money for this milestone is. Null until released.
 * @property-read Refund|null $refund Where the buyer's money for this milestone is. Null unless refunded.
 * @property-read string|null $released_at
 * @property-read string|null $refunded_at
 */
final class Milestone extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'delivery' => Delivery::class,
        'payout' => Payout::class,
        'refund' => Refund::class,
    ];
}
