<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Send either `file` (an id from `POST /files` with purpose `agreement`) or `url` (an HTTPS link
 * KetaPay copies). JPEG, PNG, HEIC or WebP. Responses carry KetaPay's own `url` plus a small
 * `thumbnail_url`.
 *
 * @property-read string $file
 * @property-read string $url
 * @property-read string $thumbnail_url
 * @property-read string|null $alt Short description, for screen readers
 */
final class Image extends \KetaPay\KetaPayObject
{
}
