<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PipelineRepository;
use App\Contracts\Repositories\PipelineStageRepository;
use App\Exceptions\BusinessRuleException;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PipelineService
{
    public function __construct(
        private readonly PipelineRepository $pipelines,
        private readonly PipelineStageRepository $stages,
    ) {}

    /** @param array<string,mixed> $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->pipelines->paginate($filters);
    }

    public function show(Pipeline $pipeline): Pipeline
    {
        return $this->pipelines->findWithStages($pipeline->getKey());
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Pipeline
    {
        return DB::transaction(function () use ($data) {
            $data['slug'] = $this->uniquePipelineSlug($data['name']);
            if (($data['is_default'] ?? false) === true) {
                Pipeline::where('is_default', true)->update(['is_default' => false]);
            }
            return $this->pipelines->create($data);
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        return DB::transaction(function () use ($pipeline, $data) {
            if (isset($data['name']) && $data['name'] !== $pipeline->name) {
                $data['slug'] = $this->uniquePipelineSlug($data['name'], $pipeline->id);
            }
            if (($data['is_default'] ?? false) === true) {
                Pipeline::where('id', '<>', $pipeline->id)->where('is_default', true)->update(['is_default' => false]);
            }
            if (($data['is_active'] ?? $pipeline->is_active) === false && $pipeline->is_default) {
                throw new BusinessRuleException('The default pipeline cannot be deactivated. Mark another pipeline as default first.');
            }
            if (($data['is_default'] ?? $pipeline->is_default) === false && $pipeline->is_default) {
                throw new BusinessRuleException('A default pipeline must remain configured. Mark another pipeline as default first.');
            }
            return $this->pipelines->update($pipeline, $data);
        });
    }

    public function delete(Pipeline $pipeline): void
    {
        if ($pipeline->is_default) {
            throw new BusinessRuleException('The default pipeline cannot be deleted.');
        }
        if ($pipeline->deals()->exists()) {
            throw new BusinessRuleException('A pipeline containing deals cannot be deleted.');
        }
        $this->pipelines->delete($pipeline);
    }

    /** @param array<string,mixed> $data */
    public function createStage(Pipeline $pipeline, array $data): PipelineStage
    {
        return DB::transaction(function () use ($pipeline, $data) {
            if (($data['is_won'] ?? false) && ($data['is_lost'] ?? false)) {
                throw new BusinessRuleException('A pipeline stage cannot be both Won and Lost.');
            }
            $data['slug'] = $this->uniqueStageSlug($pipeline, $data['name']);
            $data['position'] ??= ((int) $pipeline->stages()->max('position')) + 1;
            return $this->stages->create($pipeline, $data);
        });
    }

    /** @param array<string,mixed> $data */
    public function updateStage(PipelineStage $stage, array $data): PipelineStage
    {
        if (($data['is_won'] ?? $stage->is_won) && ($data['is_lost'] ?? $stage->is_lost)) {
            throw new BusinessRuleException('A pipeline stage cannot be both Won and Lost.');
        }

        return DB::transaction(function () use ($stage, $data) {
            $oldSlug = $stage->slug;
            if (isset($data['name']) && $data['name'] !== $stage->name) {
                $data['slug'] = $this->uniqueStageSlug($stage->pipeline, $data['name'], $stage->id);
            }

            $updated = $this->stages->update($stage, $data);

            if ($updated->slug !== $oldSlug) {
                $updated->deals()->update(['stage' => $updated->slug]);
            }

            if ($updated->is_won || $updated->is_lost) {
                $updated->deals()->whereNull('closed_at')->update(['closed_at' => now()]);
            } else {
                $updated->deals()->update(['closed_at' => null]);
            }

            return $updated;
        });
    }

    public function deleteStage(PipelineStage $stage): void
    {
        if ($stage->deals()->exists()) {
            throw new BusinessRuleException('A stage containing deals cannot be deleted.');
        }
        if ($stage->pipeline->stages()->count() <= 1) {
            throw new BusinessRuleException('A pipeline must contain at least one stage.');
        }
        $this->stages->delete($stage);
    }

    /** @param array<int,array{id:string,position:int}> $items */
    public function reorderStages(Pipeline $pipeline, array $items): void
    {
        $ids = array_column($items, 'id');
        $existing = $pipeline->stages()->whereIn('id', $ids)->pluck('id')->all();
        sort($ids);
        sort($existing);
        if ($ids !== $existing || count($items) !== count($existing)) {
            throw new BusinessRuleException('Every stage in the reorder request must belong to this pipeline.');
        }
        $positions = array_column($items, 'position');
        if (count($positions) !== count(array_unique($positions))) {
            throw new BusinessRuleException('Stage positions must be unique.');
        }
        $this->stages->reorder($pipeline, $items);
    }

    public function board(Pipeline $pipeline): array
    {
        return $this->stages->boardStages($pipeline)->map(fn (PipelineStage $stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'slug' => $stage->slug,
            'position' => $stage->position,
            'color' => $stage->color,
            'probability' => $stage->probability,
            'is_won' => $stage->is_won,
            'is_lost' => $stage->is_lost,
            'deals_count' => (int) $stage->deals_count,
            'deals' => $stage->deals->map(fn ($deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'amount' => $deal->amount,
                'currency' => $deal->currency,
                'contact' => $deal->contact ? ['id' => $deal->contact->id, 'name' => $deal->contact->full_name] : null,
                'is_closed' => $stage->is_won || $stage->is_lost,
            ])->values()->all(),
        ])->values()->all();
    }

    private function uniquePipelineSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'pipeline';
        $slug = $base;
        $i = 2;
        while (Pipeline::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function uniqueStageSlug(Pipeline $pipeline, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'stage';
        $slug = $base;
        $i = 2;
        while ($pipeline->stages()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
