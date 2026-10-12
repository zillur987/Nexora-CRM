<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TaskRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'name', 'assignee_id', 'company_id', 'contact_id', 'opportunity_id', 'quote_id',
        'task_type_id', 'task_priority_id', 'task_status_id', 'due_date', 'description', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo { return $this->belongsTo(TaskRelations::userModel(), 'assignee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(TaskRelations::userModel(), 'created_by'); }
    public function type(): BelongsTo { return $this->belongsTo(TaskType::class, 'task_type_id'); }
    public function priority(): BelongsTo { return $this->belongsTo(TaskPriority::class, 'task_priority_id'); }
    public function status(): BelongsTo { return $this->belongsTo(TaskStatus::class, 'task_status_id'); }

    // The four below fall back to Task itself when the linked model is missing; they are never eager-loaded then
    // (see TaskRelations::available()), so the fallback is only there to keep the method type-safe.
    public function company(): BelongsTo { return $this->belongsTo(TaskRelations::modelClass('company') ?? self::class, 'company_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(TaskRelations::modelClass('contact') ?? self::class, 'contact_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(TaskRelations::modelClass('opportunity') ?? self::class, 'opportunity_id'); }
    public function quote(): BelongsTo { return $this->belongsTo(TaskRelations::modelClass('quote') ?? self::class, 'quote_id'); }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! filled($search)) return $query;
        $term = '%'.addcslashes(trim($search), '%_\\').'%';
        return $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
    }

    /** Not finished: no status, or a status that is not a "closed" one. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('task_status_id')
            ->orWhereHas('status', fn (Builder $s) => $s->where('is_closed', false)));
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $s) => $s->where('is_closed', true));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', now()->toDateString());
    }
}
