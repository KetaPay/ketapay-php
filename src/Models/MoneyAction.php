<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * One decision a business made that moves money, and who made it. KetaPay moves the money and keeps
 * this record for good.
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $action `refund` is a refund of your own sale. `ruling_*` is a platform's decision on a dispute (`dispute_id`): all to the seller, all back to the buyer, or split.
 * @property-read string $status `scheduled` is waiting for `execute_after`, `executing` is moving the money. `executed` is done. `cancelled` was overtaken or stopped before any money moved. `failed` didn't move the money, and KetaPay is looking into it.
 * @property-read int $amount
 * @property-read int|null $refund_amount For `ruling_split`, the part of `amount` that went back to the buyer; the rest went to the seller. Null otherwise
 * @property-read string $escrow_id
 * @property-read string|null $milestone_id
 * @property-read string|null $dispute_id
 * @property-read string $reason
 * @property-read string $execute_after When the money moves. A refund moves at once
 * @property-read string|null $executed_at
 * @property-read string $created_at
 */
final class MoneyAction extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'money_action'];
}
