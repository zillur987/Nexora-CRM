<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuItemType: string
{
    case Link = 'link';
    case Mega = 'mega';
    case Column = 'column';
    case Item = 'item';

    /** The type the parent must have (null = top level). */
    public function parentType(): ?self
    {
        return match ($this) {
            self::Link, self::Mega => null,
            self::Column => self::Mega,
            self::Item => self::Column,
        };
    }
}
