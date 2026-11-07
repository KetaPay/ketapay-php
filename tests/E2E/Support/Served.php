<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Support;

/**
 * Parts of the spec the /v1 API doesn't serve yet: their routes answer 404, or the field is always null.
 * Scenarios that need one are skipped (or skip that check) until the API ships it. When it does, delete
 * the entry from $notServedYet and the scenarios run again on their own.
 *
 *   bank_transfer     POST /checkout_sessions/{id}/bank_transfer
 *   release_code      GET /escrows/{id}/milestones/{milestone_id}/release_code
 *   disputes          /escrows/{id}/disputes, /disputes/*
 *   chargebacks       /escrows/{id}/chargebacks, /chargebacks/*
 *   files             POST /files
 *   payout_accounts   /banks, /payout_accounts/*
 *   milestone_payout  milestone.payout and milestone.refund
 */
final class Served
{
    /** @var list<string> Empty while the API serves everything; a property, so the type stays list<string> */
    private static array $notServedYet = [];

    /** True when the API serves every feature listed. */
    public static function all(string ...$features): bool
    {
        foreach ($features as $feature) {
            if (in_array($feature, self::$notServedYet, true)) {
                return false;
            }
        }

        return true;
    }
}
