<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads\Rules;

use Illuminate\Validation\Rule;

final class LeadRules
{
    public const SOURCES = [
        'website', 'referral', 'social_media', 'email_campaign',
        'cold_call', 'event', 'partner', 'other',
    ];

    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED'];

    /**
     * Rules shared by the create form, the update form and the CSV import.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'string', 'email:rfc', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'company'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'job_title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'source'          => ['sometimes', 'string', Rule::in(self::SOURCES)],
            'score'           => ['sometimes', 'integer', 'between:0,100'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency'        => ['sometimes', 'string', Rule::in(self::CURRENCIES)],
            'assigned_to'     => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}