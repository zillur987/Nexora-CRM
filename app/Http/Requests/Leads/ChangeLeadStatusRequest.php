<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // "converted" has its own endpoint because it creates a contact.
            'status' => ['required', Rule::enum(LeadStatus::class)->except([LeadStatus::Converted])],
        ];
    }

    public function status(): LeadStatus
    {
        return LeadStatus::from($this->validated('status'));
    }
}
