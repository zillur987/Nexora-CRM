<?php // database/seeders/DefaultPipelineSeeder.php

namespace Database\Seeders;

use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class DefaultPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = Pipeline::firstOrCreate(
            ['name' => 'Sales Pipeline'],
            ['is_default' => true],
        );

        if ($pipeline->stages()->exists()) {
            return;
        }

        $stages = [
            ['Qualification', 10, 'open', '#94a3b8'],
            ['Needs Analysis', 25, 'open', '#60a5fa'],
            ['Proposal', 50, 'open', '#a78bfa'],
            ['Negotiation', 75, 'open', '#f59e0b'],
            ['Won', 100, 'won', '#22c55e'],
            ['Lost', 0, 'lost', '#ef4444'],
        ];

        foreach ($stages as $i => [$name, $prob, $type, $color]) {
            $pipeline->stages()->create([
                'name' => $name, 'position' => $i,
                'probability' => $prob, 'type' => $type, 'color' => $color,
            ]);
        }
    }
}