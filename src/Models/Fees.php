<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Totals for the whole escrow, in kobo. `buyer_total = amount + buyer_fee + buyer_vat` and
 * `seller_payout = amount - seller_fee - commission`. Until everything is released these are what will
 * apply; each milestone shows its own share.
 *
 * @property-read int $amount
 * @property-read int $buyer_fee KetaPay's fee paid by the buyer (1.5%)
 * @property-read int $buyer_vat 7.5% VAT on `buyer_fee`
 * @property-read int $buyer_total What the buyer is charged
 * @property-read int $seller_fee KetaPay's fee taken from the seller's payouts (1.5%)
 * @property-read int $commission Your commission
 * @property-read int $seller_payout What the seller receives once everything is released
 */
final class Fees extends \KetaPay\KetaPayObject
{
}
