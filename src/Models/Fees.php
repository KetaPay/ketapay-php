<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Totals for the whole escrow, in kobo. KetaPay charges one fee per escrow, `rate.fee_bps` of the
 * amount plus `rate.fee_flat`, with VAT at `rate.vat_bps` on the fee. Who pays it is your fee setting:
 * the buyer, the seller, split in half (the buyer's half takes the odd kobo), or your platform out of
 * its commission. `buyer_total = amount + buyer_fee + buyer_vat` and `seller_payout = amount -
 * seller_fee - seller_vat - commission`. Until everything is released these are what will apply; each
 * milestone shows its own share, and the flat part is charged once per escrow, not per milestone.
 *
 * @property-read int $amount
 * @property-read int $buyer_fee The buyer's share of KetaPay's fee, charged with the amount
 * @property-read int $buyer_vat VAT on `buyer_fee`
 * @property-read int $buyer_total What the buyer is charged
 * @property-read int $seller_fee The seller's share of KetaPay's fee, taken from the seller's payouts
 * @property-read int $seller_vat VAT on `seller_fee`
 * @property-read int $platform_fee When your platform pays the fee, all of it, taken from your commission at release. Otherwise 0.
 * @property-read int $platform_vat VAT on `platform_fee`
 * @property-read int $commission Your commission, before `platform_fee` and `platform_vat` come out of it
 * @property-read int $seller_payout What the seller receives once everything is released
 * @property-read Rate $rate
 */
final class Fees extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'rate' => Rate::class,
    ];
}
