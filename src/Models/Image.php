<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Send `url`, an HTTPS link to a JPEG, PNG, HEIC or WebP. KetaPay copies the image when the session is
 * created. Responses carry KetaPay's own `url` plus a small `thumbnail_url`.
 *
 * @property-read string $url In a request, your HTTPS link. In a response, a link to KetaPay's copy, valid for 1 hour.
 * @property-read string $thumbnail_url
 * @property-read string|null $alt Short description, for screen readers
 */
final class Image extends \KetaPay\KetaPayObject
{
}
