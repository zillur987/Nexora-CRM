<?php // app/Actions/Deals/CreateDeal.php

namespace App\Actions\Deals;

use App\Data\DealData;
use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDeal
{
    public function handle(DealData $data): Deal
    {
        return DB::transaction(function () use ($data) {
            $pipeline = Pipeline::findOrFail($data->pipelineId);

            $stage = $data->stageId
                ? PipelineStage::where('pipeline_id', $pipeline->id)->find($data->stageId)
                : $pipeline->stages()->first();

            if (! $stage) {
                throw ValidationException::withMessages([
                    'stage_id' => 'Stage does not belong to this pipeline.',
                ]);
            }

            // Append to the bottom of the stage.
            $position = Deal::where('stage_id', $stage->id)->count();

            return Deal::create([
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'owner_id' => $data->ownerId,
                'contact_id' => $data->contactId,
                'company_id' => $data->companyId,
                'title' => $data->title,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'probability' => $stage->probability,
                'position' => $position,
                'status' => $stage->type->toDealStatus() ?? DealStatus::Open,
                'expected_close_date' => $data->expectedCloseDate,
                'stage_entered_at' => now(),
                'custom_fields' => $data->customFields,
            ]);
        });
    }
}