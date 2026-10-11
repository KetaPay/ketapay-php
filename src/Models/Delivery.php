<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Delivery
 *
 * @property-read string|null $note
 * @property-read list<Attachment> $attachments
 * @property-read string $delivered_at
 */
final class Delivery extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'attachments' => Attachment::class,
    ];
}
