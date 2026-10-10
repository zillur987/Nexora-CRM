<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DealStageOutcome;
use App\Models\DealStage;
use App\Models\DealType;
use Illuminate\Database\Seeder;

/**
 * Starter pipeline and deal types. Safe to run repeatedly: existing rows (and any edits
 * made to them) are never overwritten.
 */
class DealLookupSeeder extends Seeder
{
    /** name => [default probability %, outcome]; array order is the pipeline order. */
    private const STAGES = [
        'Qualification' => [10, DealStageOutcome::Open],
        'Needs analysis' => [25, DealStageOutcome::Open],
        'Proposal' => [50, DealStageOutcome::Open],
        'Negotiation' => [75, DealStageOutcome::Open],
        'Closed won' => [100, DealStageOutcome::Won],
        'Closed lost' => [0, DealStageOutcome::Lost],
    ];

    private const TYPES = ['New business', 'Existing business', 'Renewal', 'Upsell / cross-sell', 'Other'];

    public function run(): void
    {
        $order = 0;

        foreach (self::STAGES as $name => [$probability, $outcome]) {
            DealStage::query()->firstOrCreate(
                ['name' => $name],
                ['probability' => $probability, 'outcome' => $outcome, 'sort_order' => ++$order],
            );
        }

        foreach (self::TYPES as $name) {
            DealType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
