<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Payout
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $escrow_id
 * @property-read string $milestone_id
 * @property-read int $amount
 * @property-read string $status `awaiting_verification`: held until the seller verifies their identity (platforms only: a seller you name is paid once they have verified, any amount; a business selling for itself is never held, nor is the seller of a platform KetaPay has approved to verify its own sellers). `awaiting_account`: in the seller's KetaPay balance until they confirm a payout account. `processing`: transfer started. `sent`: reached the bank. `failed`: rejected by the bank and back in the seller's balance.
 * @property-read string|null $bank_name
 * @property-read string|null $account_number_last4
 * @property-read string|null $sent_at
 * @property-read string|null $failure_reason
 * @property-read string|null $verification_url Where the seller verifies, while the payout is `awaiting_verification`: send them there. It is a KetaPay page, the same for every payout of the seller, and needs no sign-in: the seller checks their BVN and takes a selfie on their phone. Once they finish, every payout held for them goes on to their payout account. In test mode, starting it on the page verifies the seller at once. Null in every other status.
 */
final class Payout extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'payout'];
}
