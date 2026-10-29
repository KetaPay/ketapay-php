<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Support;

/**
 * Parts of the spec the /v1 API doesn't serve yet: their routes answer 404, or the field is always null.
 * Scenarios that need one are skipped (or skip that check) until the API ships it. When it does, delete
 * the entry from NOT_SERVED_YET and the scenarios run again on their own.
 *
 *   bank_transfer     POST /checkout_sessions/{id}/bank_transfer
 *   release_code      GET /escrows/{id}/milestones/{milestone_id}/release_code
 *   escrow_cancel     POST /escrows/{id}/cancel
 *   disputes          /escrows/{id}/disputes, /disputes/*
 *   chargebacks       /escrows/{id}/chargebacks, /chargebacks/*
 *   files             POST /files
 *   payout_accounts   /banks, /payout_accounts/*
 *   simulate          POST /test_helpers/escrows/{id}/simulate
 *   milestone_payout  milestone.payout and milestone.refund
 */
final class Served
{
    private const NOT_SERVED_YET = [
        'bank_transfer',
        'release_code',
        'escrow_cancel',
        'chargebacks',
        'payout_accounts',
        'simulate',
        'milestone_payout', // always null for now
    ];

    /** True when the API serves every feature listed. */
    public static function all(string ...$features): bool
    {
        foreach ($features as $feature) {
            if (in_array($feature, self::NOT_SERVED_YET, true)) {
                return false;
            }
        }

        return true;
    }
}
