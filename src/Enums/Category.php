<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Enums;

/**
 * What kind of deal this is. KetaPay uses it for risk checks and dispute handling.
 *
 * New values can be added to the API at any time: treat unknown ones gracefully.
 */
final class Category
{
    public const GOODS = 'goods';
    public const SERVICES = 'services';
    public const PROPERTY = 'property';
    public const VEHICLE = 'vehicle';
    public const DIGITAL = 'digital';
    public const OTHER = 'other';
}
