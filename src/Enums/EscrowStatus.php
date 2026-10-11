<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * `active`: money held, milestones in progress. `disputed`: the buyer reported a problem; KetaPay is
 * handling it. `completed`: ended with at least one milestone paid to the seller. `refunded`: ended
 * with everything returned to the buyer. An escrow only exists once the buyer has paid.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class EscrowStatus
{
    public const ACTIVE = 'active';
    public const DISPUTED = 'disputed';
    public const COMPLETED = 'completed';
    public const REFUNDED = 'refunded';
}
