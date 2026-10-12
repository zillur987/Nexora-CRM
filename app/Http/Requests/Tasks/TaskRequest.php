<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Support\TaskRelations;
use Illuminate\Foundation\Http\FormRequest;

/** Shared field rules for Store/Update. Update passes $partial = true so every field becomes "sometimes". */
abstract class TaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => trim($this->input('name'))]);
    }

    protected function payloadRules(bool $partial): array
    {
        $lead = $partial ? ['sometimes'] : [];
        $link = fn (string $key) => [...$lead, 'nullable', 'integer', ...TaskRelations::existsRule($key)];

        return [
            'name' => [...$lead, 'required', 'string', 'max:180'],
            'assignee_id' => [...$lead, 'nullable', 'integer', 'exists:'.TaskRelations::userTable().',id'],
            'company_id' => $link('company'),
            'contact_id' => $link('contact'),
            'opportunity_id' => $link('opportunity'),
            'quote_id' => $link('quote'),
            'task_type_id' => [...$lead, 'nullable', 'integer', 'exists:task_types,id'],
            'task_priority_id' => [...$lead, 'nullable', 'integer', 'exists:task_priorities,id'],
            'task_status_id' => [...$lead, 'nullable', 'integer', 'exists:task_statuses,id'],
            'due_date' => [...$lead, 'nullable', 'date_format:Y-m-d'],
            'description' => [...$lead, 'nullable', 'string', 'max:20000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'task name',
            'assignee_id' => 'assignee',
            'company_id' => 'company',
            'contact_id' => 'contact',
            'opportunity_id' => 'opportunity',
            'quote_id' => 'quote',
            'task_type_id' => 'task type',
            'task_priority_id' => 'task priority',
            'task_status_id' => 'task status',
            'due_date' => 'due date',
        ];
    }
}
