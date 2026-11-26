<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\VerificationLink;

/**
 * Links for a seller to verify their identity once, coming back to your app or site.
 *
 * @phpstan-import-type CreateVerificationLinkRequest from \KetaPay\Types\Params
 * @phpstan-import-type CustomerRequestOptions from \KetaPay\KetaPay
 */
final class VerificationLinks extends AbstractResource
{
    /**
     * A link for the customer whose token you send to verify (their BVN and a selfie). Send them to
     * its url; once verified, the page offers a button back to return_url. Works for 24 hours.
     *
     * @param CreateVerificationLinkRequest $params  return_url, refresh_url
     * @param CustomerRequestOptions        $options
     */
    public function create(array $params, array $options): VerificationLink
    {
        return $this->object(VerificationLink::class, 'POST', '/verification_links', $params, self::customer($options));
    }
}
