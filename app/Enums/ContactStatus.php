<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Bootstrap contextual colour. */
    public function badge(): string
    {
        return match ($this) {
            self::Lead => 'info',
            self::Active => 'success',
            self::Inactive => 'secondary',
        };
    }
}
