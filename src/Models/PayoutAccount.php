<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * PayoutAccount
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read string $status `pending_confirmation`: waiting for the seller's code. `active`: receives payouts (one per seller). `inactive`: replaced by a newer confirmed account. `cancelled`: not confirmed in time, or too many wrong codes.
 * @property-read string $bank_code
 * @property-read string $bank_name
 * @property-read string $account_number_last4
 * @property-read string $account_name The name the bank holds for this account. Shown to the seller before they confirm; it does not have to match their name.
 * @property-read string $created_at
 * @property-read string|null $confirmed_at
 */
final class PayoutAccount extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'payout_account'];
}
