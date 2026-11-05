<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * The rate your next escrow is priced on. Your rate each month follows your completed volume through
 * KetaPay in the previous calendar month; a launch partner or negotiated rate replaces the standard
 * one until `ends_at`. Show these figures to your users instead of writing the rate into your site or
 * app.
 *
 * @property-read string $object
 * @property-read string $plan
 * @property-read string $name
 * @property-read string $kind `standard`: the public rate card. `launch`: the launch partner rate. `custom`: a rate agreed with you.
 * @property-read int $fee_bps The percentage part of the fee, in basis points
 * @property-read int $fee_flat The fixed part of the fee, once per escrow
 * @property-read int $vat_bps
 * @property-read string $fees_paid_by Who pays the fee on your escrows, set in your dashboard. `platform` is for platforms, paid out of your commission.
 * @property-read int $min_amount The smallest escrow you can create (`amount_too_small` below it)
 * @property-read int|null $max_amount The largest escrow your rate covers, in kobo (`amount_requires_custom_rate` above it). Null for no limit.
 * @property-read string|null $ends_at When a launch partner or negotiated rate ends and your standard rate applies again. Null on the standard rate.
 */
final class Pricing extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'pricing'];
}
