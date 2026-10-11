<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Send either `file` (an id from `POST /files`) or `url` (an HTTPS link KetaPay copies, so it survives
 * your copy being deleted). Responses always carry KetaPay's own `url`.
 *
 * @property-read string $name
 * @property-read string $file
 * @property-read string $url
 */
final class Attachment extends \KetaPay\KetaPayObject
{
}
