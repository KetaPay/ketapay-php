<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * EventData
 *
 * @property-read CheckoutSession|Escrow|Dispute|Payout|Refund|Chargeback $object The object as it was when the event happened
 * @property-read string|null $milestone_id For `milestone.*` events, the milestone that changed
 */
final class EventData extends \KetaPay\KetaPayObject
{
}
