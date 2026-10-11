<?php

// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.

declare(strict_types=1);

namespace KetaPay\Models;

/**
 * Escrow
 *
 * @property-read string $id
 * @property-read string $object
 * @property-read bool $livemode
 * @property-read string $status See \KetaPay\Enums\EscrowStatus.
 * @property-read int $amount
 * @property-read string $currency
 * @property-read Fees $fees
 * @property-read string $category See \KetaPay\Enums\Category.
 * @property-read string $title
 * @property-read string $description
 * @property-read list<Image> $images KetaPay's copies of the images sent at checkout; the first is the cover
 * @property-read list<Attachment> $attachments KetaPay's copies of the documents sent at checkout
 * @property-read list<Milestone> $milestones In order. One entry for a single payment.
 * @property-read Party $buyer
 * @property-read Party $seller
 * @property-read Release $release
 * @property-read string $checkout_session_id
 * @property-read string|null $open_dispute_id The dispute currently open, if any. `GET /escrows/{id}/disputes` lists them all.
 * @property-read string|null $open_chargeback_id A card chargeback currently being contested, if any
 * @property-read bool $flagged Whether you have flagged this escrow
 * @property-read list<TimelineEntry> $timeline What happened, oldest first
 * @property-read string|null $external_reference
 * @property-read \KetaPay\KetaPayObject $metadata
 * @property-read string $created_at
 * @property-read string $funded_at
 * @property-read string|null $ended_at When it became `completed` or `refunded`
 */
final class Escrow extends \KetaPay\KetaPayObject
{
    protected const NESTED = [
        'fees' => Fees::class,
        'images' => Image::class,
        'attachments' => Attachment::class,
        'milestones' => Milestone::class,
        'buyer' => Party::class,
        'seller' => Party::class,
        'release' => Release::class,
        'timeline' => TimelineEntry::class,
    ];
}
