<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'stage' => ['sometimes', Rule::enum(DealStage::class)],
            'contact_id' => ['sometimes', 'uuid'],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'amount', 'expected_close_date'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
