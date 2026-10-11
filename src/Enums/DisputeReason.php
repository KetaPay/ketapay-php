<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * Buyer reasons: `not_received` (goods or work never arrived), `not_as_agreed` (differs from what was
 * agreed), `damaged`, `incomplete` (only part was delivered). Seller reason: `buyer_unresponsive`
 * (delivered, but the buyer won't release or respond). Either side: `other`.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class DisputeReason
{
    public const NOT_RECEIVED = 'not_received';
    public const NOT_AS_AGREED = 'not_as_agreed';
    public const DAMAGED = 'damaged';
    public const INCOMPLETE = 'incomplete';
    public const BUYER_UNRESPONSIVE = 'buyer_unresponsive';
    public const OTHER = 'other';
}
