<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuotesRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(QuoteStatus::class)],
            'company_id' => ['sometimes', 'nullable', 'integer'],
            'contact_id' => ['sometimes', 'nullable', 'integer'],
            'deal_id' => ['sometimes', 'nullable', 'integer'],
            'issue_from' => ['sometimes', 'nullable', 'date'],
            'issue_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_from'],
            // a user id or the literal "unassigned"
            'owner_id' => ['sometimes', 'nullable', 'string', 'regex:/^(\d+|unassigned)$/'],
            'sort_by' => ['sometimes', 'nullable', Rule::in(['created_at', 'quote_number', 'total', 'issue_date', 'valid_until'])],
            'sort_dir' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
