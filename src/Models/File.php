<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * File
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read string $purpose See \KetaPay\Enums\FilePurpose.
 * @property-read string $name
 * @property-read string $mime_type
 * @property-read int $size_bytes
 * @property-read string $url Short-lived link to download the file, valid for 1 hour
 * @property-read string $created_at
 */
final class File extends \KetaPay\KetaPayObject
{
    protected const MATCH = ['object' => 'file'];
}
