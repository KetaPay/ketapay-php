<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Dispute
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $escrow_id
 * @property-read string|null $milestone_id Null when the dispute covers the whole escrow
 * @property-read string $opened_by
 * @property-read string $status `open`: the buyer and seller are trying to agree. `under_review`: a KetaPay mediator is deciding. `resolved`: ended, see `outcome`.
 * @property-read string $reason See \KetaPay\Enums\DisputeReason.
 * @property-read string|null $outcome Null until resolved. `withdrawn`: the buyer withdrew the dispute and the deal continues. `partially_refunded`: split between buyer and seller.
 * @property-read string|null $resolved_by Who ended it. A party can only end it against themselves (conceding) or by withdrawing their own dispute.
 * @property-read int|null $refunded_amount For `refunded` and `partially_refunded`, the amount returned to the buyer
 * @property-read string|null $url A KetaPay page for the dispute. Always null for now: KetaPay runs the dispute and the buyer and seller act through your UI (concede, withdraw, evidence).
 * @property-read string|null $awaiting_evidence_from Who KetaPay is waiting on for evidence. `both` until `evidence_due_at` passes or the dispute ends, then null
 * @property-read string|null $evidence_due_at When the evidence window ends, 72 hours after opening. It moves no money, and late evidence is still taken
 * @property-read int $evidence_count How many evidence submissions both sides have made
 * @property-read string $opened_at
 * @property-read string|null $resolved_at
 */
final class Dispute extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'dispute'];
}
