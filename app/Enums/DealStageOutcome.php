<?php

declare(strict_types=1);

namespace App\Enums;

/** What reaching a pipeline stage means for a deal. */
enum DealStageOutcome: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
