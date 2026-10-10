<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\DealStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $pipeline = Pipeline::query()->withTrashed()->firstOrCreate(
                ['name' => 'Sales Pipeline'],
                ['description' => 'Default sales process', 'is_default' => true, 'is_active' => true, 'sort_order' => 0],
            );
            if ($pipeline->trashed()) {
                $pipeline->restore();
            }

            // Keep an existing default pipeline if one was already configured.
            if (! Pipeline::query()->where('is_default', true)->whereKeyNot($pipeline->id)->exists()) {
                $pipeline->forceFill(['is_default' => true])->save();
            }

            $defaults = [
                ['name' => 'Qualification', 'probability' => 10, 'outcome' => 'open', 'sort_order' => 10],
                ['name' => 'Needs Analysis', 'probability' => 25, 'outcome' => 'open', 'sort_order' => 20],
                ['name' => 'Proposal', 'probability' => 50, 'outcome' => 'open', 'sort_order' => 30],
                ['name' => 'Negotiation', 'probability' => 75, 'outcome' => 'open', 'sort_order' => 40],
                ['name' => 'Won', 'probability' => 100, 'outcome' => 'won', 'sort_order' => 50],
                ['name' => 'Lost', 'probability' => 0, 'outcome' => 'lost', 'sort_order' => 60],
            ];

            // Assign existing stages to the default pipeline without duplicating their names.
            DealStage::query()->whereNull('pipeline_id')->orderBy('sort_order')->get()
                ->each(function (DealStage $stage, int $index) use ($pipeline): void {
                    $stage->forceFill(['pipeline_id' => $pipeline->id, 'sort_order' => max(1, $index + 1) * 10])->save();
                });

            foreach ($defaults as $stage) {
                DealStage::query()->firstOrCreate(
                    ['pipeline_id' => $pipeline->id, 'name' => $stage['name']],
                    [...$stage, 'is_active' => true],
                );
            }
        });
    }
}
