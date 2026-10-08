<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts\Rules;

use Illuminate\Validation\Rule;

/** Single source of truth for contact validation: the create and update requests both build on this. */
final class ContactRules
{
    /** Normalised to https:// when the user types a bare domain. */
    public const URL_FIELDS = ['linkedin', 'twitter'];

    /** Every free-text field that is trimmed before validation. */
    public const TEXT_FIELDS = [
        'first_name', 'last_name', 'job_title', 'phone', 'department', 'description',
        'present_address', 'present_city', 'present_zip', 'present_state', 'present_country',
        'permanent_address', 'permanent_city', 'permanent_zip', 'permanent_state', 'permanent_country',
    ];

    /**
     * Rules for creating a contact. `email` uniqueness is added by the request
     * because it differs between create and update.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'birthday' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],

            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'job_title' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'min:5', 'max:30'],
            'department' => ['nullable', 'string', 'max:100'],

            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'contact_source_id' => ['nullable', 'integer', 'exists:contact_sources,id'],
            'contact_stage_id' => ['nullable', 'integer', 'exists:contact_stages,id'],

            'present_address' => ['nullable', 'string', 'max:255'],
            'present_city' => ['nullable', 'string', 'max:100'],
            'present_zip' => ['nullable', 'string', 'max:20'],
            'present_state' => ['nullable', 'string', 'max:100'],
            'present_country' => ['nullable', 'string', 'max:100'],

            'permanent_address' => ['nullable', 'string', 'max:255'],
            'permanent_city' => ['nullable', 'string', 'max:100'],
            'permanent_zip' => ['nullable', 'string', 'max:20'],
            'permanent_state' => ['nullable', 'string', 'max:100'],
            'permanent_country' => ['nullable', 'string', 'max:100'],

            'twitter' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin' => ['nullable', 'url:http,https', 'max:255'],

            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Partial-update variant: every field is optional, but when present it must still be valid.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function forUpdate(): array
    {
        return array_map(
            fn (array $rules) => in_array('sometimes', $rules, true) ? $rules : ['sometimes', ...$rules],
            self::attributes(),
        );
    }
}
