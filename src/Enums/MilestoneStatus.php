<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * `funded`: money held, waiting for the seller. `delivered`: the seller says it is done; waiting for
 * the buyer. `released`: paid to the seller. `refunded`: returned to the buyer. `disputed`: frozen
 * until KetaPay resolves the dispute.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class MilestoneStatus
{
    public const FUNDED = 'funded';
    public const DELIVERED = 'delivered';
    public const RELEASED = 'released';
    public const REFUNDED = 'refunded';
    public const DISPUTED = 'disputed';
}
