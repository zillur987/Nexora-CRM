<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('tasks.view') ?? false; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', Rule::in(['open', 'overdue', 'today', 'completed'])],
            'assignee_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
            'quote_id' => ['nullable', 'integer'],
            'task_type_id' => ['nullable', 'integer'],
            'task_priority_id' => ['nullable', 'integer'],
            'task_status_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['name', 'due_date', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
