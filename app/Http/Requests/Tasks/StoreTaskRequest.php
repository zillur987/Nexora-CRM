<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

class StoreTaskRequest extends TaskRequest
{
    public function authorize(): bool { return $this->user()?->can('tasks.create') ?? false; }
    public function rules(): array { return $this->payloadRules(false); }
}
