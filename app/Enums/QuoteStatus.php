<?php

declare(strict_types=1);

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Still waiting on the customer (or on us): counts as pipeline and can expire. */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }
}
