<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Release
 *
 * @property-read string $method `buyer_confirms`: the seller delivers each milestone and the buyer releases it. `release_code`: in person; the buyer gives the seller a 6-digit code per milestone once satisfied, and the seller entering it releases that milestone.
 * @property-read int|null $auto_release_hours `buyer_confirms` only. Hours after a milestone is delivered before it releases on its own if the buyer neither releases it nor reports a problem. Null means it never releases without the buyer. Typical values: 48 for goods, 168 for services.
 */
final class Release extends \KetaPay\KetaPayObject
{
}
