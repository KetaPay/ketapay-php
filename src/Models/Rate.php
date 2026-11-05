<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * The pricing plan the escrow is priced on. One fee per escrow, `fee_bps` of the amount plus
 * `fee_flat`.
 *
 * @property-read string $plan The pricing plan's code
 * @property-read int $fee_bps The percentage part of the fee, in basis points (175 = 1.75%)
 * @property-read int $fee_flat The fixed part of the fee, once per escrow
 * @property-read int $vat_bps VAT on the fee, in basis points
 */
final class Rate extends \KetaPay\KetaPayObject
{
}
