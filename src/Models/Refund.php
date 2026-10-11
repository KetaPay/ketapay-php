<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Refund
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $escrow_id
 * @property-read string $milestone_id
 * @property-read int $amount
 * @property-read string $destination Where the money went. `ketapay_balance` after a failed card or bank refund.
 * @property-read string $status `processing`: started. `sent`: accepted by the card network or bank. `failed`: could not go back the way it came; a new refund with `destination` `ketapay_balance` follows straight away.
 * @property-read string|null $last4 Last 4 digits of the card or bank account
 * @property-read string|null $sent_at
 * @property-read string|null $failure_reason
 */
final class Refund extends \KetaPay\KetaPayObject
{
}
