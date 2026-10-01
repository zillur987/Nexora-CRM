<?php // app/Events/DealStageChanged.php
// Hook workflow automation (Phase 6), notifications and webhooks onto this.

namespace App\Events;

use App\Models\Deal;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class DealStageChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Deal $deal,
        public int $fromStageId,
        public int $toStageId,
    ) {}
}