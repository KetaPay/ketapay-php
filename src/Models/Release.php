<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Release
 *
 * @property-read string $method `buyer_confirms`: the seller delivers each milestone and the buyer releases it. `release_code`: in person; the buyer gives the seller a 6-digit code per milestone once satisfied, and the seller entering it releases that milestone.
 * @property-read int $inspection_hours The buyer's inspection period: hours after each delivery to release the milestone, request changes or report a problem. Nothing releases when it ends; the seller can then open a `buyer_unresponsive` dispute and KetaPay decides. A change request stops it, and the next delivery starts a fresh one. Typical values: 48 for goods, 168 for services.
 */
final class Release extends \KetaPay\KetaPayObject
{
}
