<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Service = 'service';
}
