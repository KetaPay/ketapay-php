<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Payout
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $escrow_id
 * @property-read string $milestone_id
 * @property-read int $amount
 * @property-read string $status `awaiting_account`: in the seller's KetaPay balance until they confirm a payout account. `processing`: transfer started. `sent`: reached the bank. `failed`: rejected by the bank and back in the seller's balance.
 * @property-read string|null $bank_name
 * @property-read string|null $account_number_last4
 * @property-read string|null $sent_at
 * @property-read string|null $failure_reason
 */
final class Payout extends \KetaPay\KetaPayObject
{
}
