<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

class UpdateTaskRequest extends TaskRequest
{
    public function authorize(): bool { return $this->user()?->can('tasks.edit') ?? false; }
    public function rules(): array { return $this->payloadRules(true); }
}
