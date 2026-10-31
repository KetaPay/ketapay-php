<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/** The model class for each `object` value. Internal. */
final class ObjectTypes
{
    /** @var array<string, class-string<\KetaPay\KetaPayObject>> */
    public const MAP = [
        'bank_transfer_instructions' => BankTransferInstructions::class,
        'chargeback' => Chargeback::class,
        'checkout_session' => CheckoutSession::class,
        'customer_session' => CustomerSession::class,
        'customer_token' => CustomerToken::class,
        'dispute' => Dispute::class,
        'escrow' => Escrow::class,
        'event' => Event::class,
        'evidence' => Evidence::class,
        'file' => File::class,
        'flag' => Flag::class,
        'milestone' => Milestone::class,
        'money_action' => MoneyAction::class,
        'payout' => Payout::class,
        'payout_account' => PayoutAccount::class,
        'refund' => Refund::class,
        'release_code' => ReleaseCode::class,
        'webhook_endpoint' => WebhookEndpoint::class,
        'webhook_test_result' => WebhookTestResult::class,
    ];
}
