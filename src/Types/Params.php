<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Types;

/**
 * Request parameter shapes, for static analysis and autocompletion (PHPStan, Psalm, PhpStorm). The SDK
 * sends arrays as given and KetaPay validates them, so these only help you catch mistakes earlier.
 *
 * @phpstan-type CommissionInput array{rate_bps: int, min_amount?: int, max_amount?: int|null}
 * @phpstan-type CreateCheckoutSessionRequest array{amount: int, currency?: 'NGN', title: string, description: string, images?: list<array{url: string, alt?: string|null}>, attachments?: list<array{name?: string, url: string}>, url?: string|null, milestones?: list<MilestoneInput>, buyer: PartyInput, seller?: PartyInput, release?: array{method?: 'buyer_confirms'|'release_code', inspection_hours?: int}, commission?: CommissionInput|null, expires_at?: string, return_url?: string, cancel_url?: string|null, external_reference?: string|null, metadata?: array<string, string>}
 * @phpstan-type CreateCustomerSessionRequest array{email: string}|array{phone: string}
 * @phpstan-type CreateDisputeRequest array{milestone_id?: string|null, reason: 'not_received'|'not_as_agreed'|'damaged'|'incomplete'|'buyer_unresponsive'|'other', description: string, files?: list<string>}
 * @phpstan-type CreateFlagRequest array{reason: 'fraudulent_listing'|'banned_seller'|'banned_buyer'|'suspicious_activity'|'other', note?: string|null}
 * @phpstan-type CreatePayoutAccountRequest array{bank_code: string, account_number: string}
 * @phpstan-type CreateRefundRequest array{reason: string}
 * @phpstan-type CreateVerificationLinkRequest array{return_url: string, refresh_url?: string}
 * @phpstan-type CreateWebhookEndpointRequest array{url: string, enabled_events?: list<'checkout_session.expired'|'escrow.funded'|'milestone.delivered'|'milestone.changes_requested'|'milestone.released'|'escrow.completed'|'escrow.refunded'|'dispute.opened'|'dispute.evidence_requested'|'dispute.evidence_submitted'|'dispute.resolved'|'payout.sent'|'refund.sent'|'refund.failed'|'chargeback.opened'|'chargeback.closed'|'payout.failed'|'*'>, description?: string|null, metadata?: array<string, string>}
 * @phpstan-type DeliverMilestoneRequest array{note?: string|null, attachments?: list<array{name?: string, file: string, url?: string}|array{name?: string, file?: string, url: string}>}
 * @phpstan-type ListCheckoutSessionsParams array{limit?: int, cursor?: string, external_reference?: string, status?: 'open'|'complete'|'expired'|'cancelled'}
 * @phpstan-type ListEscrowsParams array{limit?: int, cursor?: string, external_reference?: string, status?: 'active'|'disputed'|'completed'|'refunded', created_gte?: string, created_lt?: string}
 * @phpstan-type ListEventsParams array{limit?: int, cursor?: string, type?: 'checkout_session.expired'|'escrow.funded'|'milestone.delivered'|'milestone.changes_requested'|'milestone.released'|'escrow.completed'|'escrow.refunded'|'dispute.opened'|'dispute.evidence_requested'|'dispute.evidence_submitted'|'dispute.resolved'|'payout.sent'|'refund.sent'|'refund.failed'|'chargeback.opened'|'chargeback.closed'|'payout.failed', created_gte?: string}
 * @phpstan-type ListWebhookEndpointsParams array{limit?: int, cursor?: string}
 * @phpstan-type MilestoneInput array{title: string, description?: string|null, amount: int, due_date?: string|null}
 * @phpstan-type PartyInput array{email: string, phone?: string, name?: string|null}|array{email?: string, phone: string, name?: string|null}
 * @phpstan-type ReleaseMilestoneRequest array{release_code?: string}
 * @phpstan-type RequestChangesRequest array{note: string}
 * @phpstan-type SubmitEvidenceRequest array{statement: string, files?: list<string>}
 * @phpstan-type UpdateWebhookEndpointRequest array{url?: string, enabled_events?: list<string>, description?: string|null, status?: 'enabled'|'disabled'}
 */
final class Params
{
}
