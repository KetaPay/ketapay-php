<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Event
 *
 * @property-read string $id Deduplicate on this
 * @property-read string $object
 * @property-read string $type See \KetaPay\Enums\EventType.
 * @property-read string $api_version
 * @property-read bool $livemode
 * @property-read string $created_at
 * @property-read EventData $data
 */
final class Event extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'data' => EventData::class,
    ];
}
