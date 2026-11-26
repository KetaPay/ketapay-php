<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * VerificationLink
 *
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $url The page to send the seller to. In test mode, starting it verifies them at once.
 * @property-read string $return_url
 * @property-read string|null $refresh_url
 * @property-read string $expires_at 24 hours after it was made
 */
final class VerificationLink extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'verification_link'];
}
