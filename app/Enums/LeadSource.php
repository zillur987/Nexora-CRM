<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case EmailCampaign = 'email_campaign';
    case ColdCall = 'cold_call';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
