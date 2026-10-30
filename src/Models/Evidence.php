<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Evidence
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read string $dispute_id
 * @property-read string $submitted_by
 * @property-read string|null $statement Null for a file the customer added in the KetaPay app, which comes without one
 * @property-read list<File> $files
 * @property-read string $created_at
 */
final class Evidence extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'files' => File::class,
    ];

    protected const MATCH = ['object' => 'evidence'];
}
