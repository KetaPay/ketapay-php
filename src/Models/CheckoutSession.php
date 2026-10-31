<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * CheckoutSession
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $status See \KetaPay\Enums\CheckoutSessionStatus.
 * @property-read string $url The hosted checkout. Open it with `@ketapay/checkout` or redirect the buyer to it.
 * @property-read int $amount
 * @property-read string $currency
 * @property-read Fees $fees
 * @property-read string $title
 * @property-read string $description
 * @property-read list<Image> $images KetaPay's copies, in order; the first is the cover
 * @property-read list<Attachment> $attachments
 * @property-read string|null $url_on_platform The `url` you sent at creation
 * @property-read list<\KetaPay\KetaPayObject> $milestones The plan. One entry for a single payment.
 * @property-read \KetaPay\KetaPayObject $buyer
 * @property-read \KetaPay\KetaPayObject|BusinessSeller $seller The seller as you sent them, or your business when you sent none
 * @property-read Release $release
 * @property-read \KetaPay\KetaPayObject|null $commission
 * @property-read string|null $escrow_id Set once the buyer has paid
 * @property-read string|null $return_url
 * @property-read string|null $cancel_url
 * @property-read string|null $external_reference
 * @property-read \KetaPay\KetaPayObject $metadata
 * @property-read string $expires_at
 * @property-read string|null $completed_at
 * @property-read string|null $cancelled_at
 * @property-read string $created_at
 */
final class CheckoutSession extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'fees' => Fees::class,
        'images' => Image::class,
        'attachments' => Attachment::class,
        'seller' => [BusinessSeller::class],
        'release' => Release::class,
    ];

    protected const MATCH = ['object' => 'checkout_session'];
}
