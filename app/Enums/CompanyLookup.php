<?php

declare(strict_types=1);

namespace App\Enums;

/** The user-extensible company dropdowns. The value is the URL segment (route-model bound). */
enum CompanyLookup: string
{
    case Industries = 'industries';
    case Types = 'company-types';
}
