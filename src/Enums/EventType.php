<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * New event types may be added. Ignore types you don't handle.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class EventType
{
    public const CHECKOUT_SESSION_EXPIRED = 'checkout_session.expired';
    public const ESCROW_FUNDED = 'escrow.funded';
    public const MILESTONE_DELIVERED = 'milestone.delivered';
    public const MILESTONE_RELEASED = 'milestone.released';
    public const ESCROW_COMPLETED = 'escrow.completed';
    public const ESCROW_REFUNDED = 'escrow.refunded';
    public const DISPUTE_OPENED = 'dispute.opened';
    public const DISPUTE_EVIDENCE_REQUESTED = 'dispute.evidence_requested';
    public const DISPUTE_EVIDENCE_SUBMITTED = 'dispute.evidence_submitted';
    public const DISPUTE_RESOLVED = 'dispute.resolved';
    public const PAYOUT_SENT = 'payout.sent';
    public const REFUND_SENT = 'refund.sent';
    public const REFUND_FAILED = 'refund.failed';
    public const CHARGEBACK_OPENED = 'chargeback.opened';
    public const CHARGEBACK_CLOSED = 'chargeback.closed';
    public const PAYOUT_FAILED = 'payout.failed';
}
