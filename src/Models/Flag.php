<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Flag
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read string $escrow_id
 * @property-read string $reason
 * @property-read string|null $note
 * @property-read string $status
 * @property-read string $created_at
 */
final class Flag extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'flag'];
}
