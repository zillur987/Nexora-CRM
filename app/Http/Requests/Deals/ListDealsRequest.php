<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Enums\DealPriority;
use App\Enums\DealStageOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Empty filters arrive as null (ConvertEmptyStringsToNull), so every optional filter is nullable. */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'deal_stage_id' => ['sometimes', 'nullable', 'integer'],
            'deal_type_id' => ['sometimes', 'nullable', 'integer'],
            'lead_source_id' => ['sometimes', 'nullable', 'integer'],
            'company_id' => ['sometimes', 'nullable', 'integer'],
            'contact_id' => ['sometimes', 'nullable', 'integer'],
            'priority' => ['sometimes', 'nullable', Rule::enum(DealPriority::class)],
            'outcome' => ['sometimes', 'nullable', Rule::enum(DealStageOutcome::class)],
            'close_from' => ['sometimes', 'nullable', 'date'],
            'close_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:close_from'],
            // a user id or the literal "unassigned"
            'owner_id' => ['sometimes', 'nullable', 'string', 'regex:/^(\d+|unassigned)$/'],
            'sort_by' => ['sometimes', 'nullable', Rule::in(['created_at', 'name', 'amount', 'expected_close_date', 'probability'])],
            'sort_dir' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
