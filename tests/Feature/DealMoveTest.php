<?php // tests/Feature/DealMoveTest.php

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Database\Seeders\DefaultPipelineSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, DefaultPipelineSeeder::class]);
    $this->pipeline = Pipeline::first();
    $this->stages = $this->pipeline->stages;
    $this->user = User::factory()->create()->assignRole('admin');
});

function makeDeal($test, int $stageIndex, int $position): Deal
{
    return Deal::factory()->create([
        'pipeline_id' => $test->pipeline->id,
        'stage_id' => $test->stages[$stageIndex]->id,
        'owner_id' => $test->user->id,
        'position' => $position,
        'probability' => $test->stages[$stageIndex]->probability,
    ]);
}

it('moves a deal to another stage and updates probability and history', function () {
    $deal = makeDeal($this, 0, 0);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/deals/{$deal->id}/move", [
            'stage_id' => $this->stages[3]->id,
            'position' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.probability', 75);

    expect($deal->fresh()->stage_id)->toBe($this->stages[3]->id)
        ->and($deal->stageHistory()->count())->toBe(1);
});

it('keeps positions contiguous when reordering', function () {
    $a = makeDeal($this, 0, 0);
    $b = makeDeal($this, 0, 1);
    $c = makeDeal($this, 0, 2);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/deals/{$c->id}/move", [
            'stage_id' => $this->stages[0]->id,
            'position' => 0,
        ])->assertOk();

    expect($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1)
        ->and($b->fresh()->position)->toBe(2);
});

it('requires a lost reason when moving to a lost stage', function () {
    $deal = makeDeal($this, 0, 0);
    $lost = $this->stages->firstWhere('name', 'Lost');

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/deals/{$deal->id}/move", ['stage_id' => $lost->id, 'position' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lost_reason');

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/deals/{$deal->id}/move", [
            'stage_id' => $lost->id, 'position' => 0, 'lost_reason' => 'Budget',
        ])->assertOk()->assertJsonPath('data.status', 'lost');
});

it('hides other reps deals from users without view_all', function () {
    $rep = User::factory()->create()->assignRole('sales_rep');
    makeDeal($this, 0, 0); // owned by admin

    $this->actingAs($rep, 'sanctum')
        ->getJson("/api/v1/pipelines/{$this->pipeline->id}/board")
        ->assertOk()
        ->assertJsonPath('stages.0.deals_count', 0);
});