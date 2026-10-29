<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Proof of delivery. Send either `file` (an id from `POST /files` with purpose `delivery`, for a photo
 * or video the seller took) or `url` (an HTTPS link KetaPay copies).
 *
 * @property-read string $name
 * @property-read string $file
 * @property-read string $url
 */
final class DeliveryAttachment extends \KetaPay\KetaPayObject
{
}
