<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * `open`: waiting for payment. `complete`: paid, `escrow_id` is set. `expired`: not paid by
 * `expires_at`. `cancelled`: you cancelled it.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class CheckoutSessionStatus
{
    public const OPEN = 'open';
    public const COMPLETE = 'complete';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
}
