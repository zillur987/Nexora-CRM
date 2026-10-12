<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\TaskRelations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $label = config('tasks.user_label', 'name');
        $link = fn (string $key) => $this->whenLoaded($key, fn () => $this->{$key}
            ? ['id' => $this->{$key}->getKey(), 'name' => TaskRelations::label($key, $this->{$key})]
            : null);
        $option = fn (string $key, bool $closed = false) => $this->whenLoaded($key, fn () => $this->{$key}
            ? ['id' => $this->{$key}->id, 'name' => $this->{$key}->name, 'color' => $this->{$key}->color] + ($closed ? ['is_closed' => (bool) $this->{$key}->is_closed] : [])
            : null);

        $isClosed = (bool) $this->status?->is_closed;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => ! $isClosed && $this->due_date !== null && $this->due_date->isBefore(today()),
            'completed_at' => $this->completed_at?->toISOString(),

            'assignee_id' => $this->assignee_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'opportunity_id' => $this->opportunity_id,
            'quote_id' => $this->quote_id,
            'task_type_id' => $this->task_type_id,
            'task_priority_id' => $this->task_priority_id,
            'task_status_id' => $this->task_status_id,

            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => (string) $this->assignee->{$label}] : null),
            'company' => $link('company'),
            'contact' => $link('contact'),
            'opportunity' => $link('opportunity'),
            'quote' => $link('quote'),
            'type' => $option('type'),
            'priority' => $option('priority'),
            'status' => $option('status', true),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
