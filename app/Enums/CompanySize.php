<?php

declare(strict_types=1);

namespace App\Enums;

/** Headcount bands. The value is the stored string and the API contract. */
enum CompanySize: string
{
    case Tiny = '1-10';
    case Small = '11-50';
    case Medium = '51-200';
    case Large = '201-500';
    case XLarge = '501-1000';
    case Enterprise = '1001-5000';
    case Global = '5001+';

    public function label(): string
    {
        return "{$this->value} employees";
    }
}
