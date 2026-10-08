<?php

declare(strict_types=1);

namespace App\Enums;

/** The user-extensible contact dropdowns. The value is the URL segment (route-model bound). */
enum ContactLookup: string
{
    case Sources = 'contact-sources';
    case Stages = 'contact-stages';
}
