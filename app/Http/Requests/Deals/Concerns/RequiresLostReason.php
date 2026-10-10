<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals\Concerns;

use App\Enums\DealStageOutcome;
use App\Models\Deal;
use App\Models\DealStage;
use Illuminate\Validation\Validator;

/**
 * A deal that sits in a "lost" stage must say why. The check looks at the stage the deal will have
 * after this request (the submitted one, else the stored one) and the lost reason it will end up with.
 */
trait RequiresLostReason
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['deal_stage_id', 'lost_reason'])) {
                return;
            }

            $deal = $this->route('deal'); // null when creating
            $stageId = $this->has('deal_stage_id') ? $this->input('deal_stage_id') : $deal?->deal_stage_id;

            if (! $stageId || DealStage::query()->find($stageId)?->outcome !== DealStageOutcome::Lost) {
                return;
            }

            $reason = $this->has('lost_reason')
                ? $this->input('lost_reason')
                : ($deal instanceof Deal ? $deal->lost_reason : null);

            if (blank($reason)) {
                $validator->errors()->add('lost_reason', 'Tell us why this deal was lost.');
            }
        });
    }
}
