<?php

declare(strict_types=1);

namespace App\Enums;

/** The user-extensible deal dropdowns. The value is the URL segment (route-model bound). */
enum DealLookup: string
{
    case Stages = 'deal-stages';
    case Types = 'deal-types';

    /** Columns exposed to the client for this dropdown. */
    public function columns(): array
    {
        return match ($this) {
            self::Stages => ['id', 'name', 'probability', 'outcome', 'sort_order'],
            self::Types => ['id', 'name'],
        };
    }
}
