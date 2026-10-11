<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * CustomerSession
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string $status
 * @property-read string $channel Where the code was delivered
 * @property-read string $expires_at When the code stops working
 */
final class CustomerSession extends \KetaPay\KetaPayObject
{
}
