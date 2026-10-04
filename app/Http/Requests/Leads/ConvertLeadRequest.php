<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\DataObjects\ConvertLeadData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creatingDeal = fn (): bool => $this->boolean('create_deal');

        return [
            'create_deal' => ['sometimes', 'boolean'],
            'deal_title' => [Rule::requiredIf($creatingDeal), 'nullable', 'string', 'max:160'],
            'deal_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }

    public function data(): ConvertLeadData
    {
        return ConvertLeadData::fromArray($this->validated());
    }
}
