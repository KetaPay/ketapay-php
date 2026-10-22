<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * The seller when you sell your own goods (a checkout session sent without `seller`): your business,
 * by the name your customers see (your checkout name, else your trading name, else your legal name).
 * Releases are paid into your KetaPay balance.
 *
 * @property-read string $type
 * @property-read string $name
 */
final class BusinessSeller extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['type' => 'business'];
}
