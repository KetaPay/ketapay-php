<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * CustomerToken
 *
 * @property-read string $object
 * @property-read string $token Send as `KetaPay-Customer-Token`. Shown only here.
 * @property-read string $expires_at
 * @property-read string|null $email The customer's email on KetaPay, if known
 * @property-read string|null $phone The customer's phone on KetaPay, if known
 * @property-read bool $has_claimed_account
 */
final class CustomerToken extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'customer_token'];
}
