<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * BankTransferInstructions
 *
 * @property-read string $object
 * @property-read string $checkout_session_id
 * @property-read string $bank_name
 * @property-read string $account_number
 * @property-read string $account_name
 * @property-read int $amount Transfer exactly this (the buyer's total)
 * @property-read string $expires_at
 */
final class BankTransferInstructions extends \KetaPay\KetaPayObject
{
}
