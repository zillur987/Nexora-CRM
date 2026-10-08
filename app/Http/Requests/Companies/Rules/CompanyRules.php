<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies\Rules;

use App\Enums\CompanySize;
use Illuminate\Validation\Rule;

/** Single source of truth for company validation: the create and update requests both build on this. */
final class CompanyRules
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED'];

    /** Normalised to https:// when the user types a bare domain. */
    public const URL_FIELDS = ['website', 'linkedin', 'twitter', 'instagram', 'facebook'];

    /** Every free-text field that is trimmed before validation. */
    public const TEXT_FIELDS = [
        'name', 'phone', 'description',
        'billing_street', 'billing_city', 'billing_zip', 'billing_state', 'billing_country',
        'shipping_street', 'shipping_city', 'shipping_zip', 'shipping_state', 'shipping_country',
    ];

    /**
     * Rules for creating a company. `name` uniqueness is added by the request
     * because it differs between create and update.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'company_type_id' => ['nullable', 'integer', 'exists:company_types,id'],
            'size' => ['nullable', Rule::enum(CompanySize::class)],
            'annual_revenue' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'currency' => ['sometimes', 'string', Rule::in(self::CURRENCIES)],

            'phone' => ['nullable', 'string', 'min:5', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'twitter' => ['nullable', 'url:http,https', 'max:255'],
            'instagram' => ['nullable', 'url:http,https', 'max:255'],
            'facebook' => ['nullable', 'url:http,https', 'max:255'],

            'billing_street' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_zip' => ['nullable', 'string', 'max:20'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_country' => ['nullable', 'string', 'max:100'],

            'shipping_street' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_zip' => ['nullable', 'string', 'max:20'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_country' => ['nullable', 'string', 'max:100'],

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
