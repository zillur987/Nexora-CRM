<?php // app/Actions/Deals/MoveDealToStage.php

namespace App\Actions\Deals;

use App\Enums\StageType;
use App\Events\DealStageChanged;
use App\Models\Deal;
use App\Models\DealStageHistory;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoveDealToStage
{
    public function handle(
        Deal $deal,
        PipelineStage $target,
        int $position,
        ?User $actor = null,
        ?string $lostReason = null,
    ): Deal {
        if ($deal->pipeline_id !== $target->pipeline_id) {
            throw ValidationException::withMessages([
                'stage_id' => 'Deals cannot move between pipelines.',
            ]);
        }

        return DB::transaction(function () use ($deal, $target, $position, $actor, $lostReason) {
            // Lock the row to prevent two concurrent drags corrupting order.
            $deal = Deal::query()->lockForUpdate()->findOrFail($deal->id);

            $fromStageId = $deal->stage_id;
            $stageChanged = $fromStageId !== $target->id;
            $oldPosition = $deal->position;

            // 1) Close the gap in the source column.
            Deal::where('stage_id', $fromStageId)
                ->where('position', '>', $oldPosition)
                ->decrement('position');

            // 2) Clamp the requested index and open a slot in the target column.
            $max = Deal::where('stage_id', $target->id)->where('id', '!=', $deal->id)->count();
            $position = max(0, min($position, $max));

            Deal::where('stage_id', $target->id)
                ->where('id', '!=', $deal->id)
                ->where('position', '>=', $position)
                ->increment('position');

            // 3) Apply the move.
            $deal->stage_id = $target->id;
            $deal->position = $position;

            if ($stageChanged) {
                $secondsInPrevious = $deal->stage_entered_at
                    ? (int) $deal->stage_entered_at->diffInSeconds(now(), absolute: true)
                    : null;

                $deal->probability = $target->probability;
                $deal->status = $target->type->toDealStatus();
                $deal->stage_entered_at = now();
                $deal->closed_at = $target->isClosed() ? now() : null;
                $deal->lost_reason = $target->type === StageType::Lost ? $lostReason : null;
            }

            $deal->save();

            // 4) Record history + fire event only on a real stage change.
            if ($stageChanged) {
                DealStageHistory::create([
                    'deal_id' => $deal->id,
                    'from_stage_id' => $fromStageId,
                    'to_stage_id' => $target->id,
                    'changed_by' => $actor?->id,
                    'seconds_in_previous_stage' => $secondsInPrevious,
                ]);

                DealStageChanged::dispatch($deal, $fromStageId, $target->id);
            }

            return $deal;
        });
    }
}