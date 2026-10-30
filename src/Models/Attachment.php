<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * A document. Send `url`, an HTTPS link KetaPay copies, so it survives your copy being deleted or
 * changed. Responses carry KetaPay's own `url` and the `sha256` of what was copied.
 *
 * @property-read string $name
 * @property-read string $url In a request, your HTTPS link. In a response, a link to KetaPay's copy, valid for 1 hour.
 * @property-read string $sha256 Fingerprint of the copy, to prove the file is the one that was agreed
 */
final class Attachment extends \KetaPay\KetaPayObject
{
}
