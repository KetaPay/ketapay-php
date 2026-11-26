<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * The sandbox verified the customer (test helper)
 *
 * @property-read string $object
 * @property-read bool $verified
 */
final class CustomerVerification extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'customer_verification'];
}
