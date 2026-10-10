<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals\Rules;

use App\Enums\DealPriority;
use Illuminate\Validation\Rule;

/** Single source of truth for deal validation: the create and update requests both build on this. */
final class DealRules
{
    /** Every free-text field that is trimmed before validation. */
    public const TEXT_FIELDS = ['name', 'next_step', 'lost_reason', 'description'];

    /** Keep in sync with CURRENCIES in resources/js/utils/dealOptions.js. */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED', 'SAR', 'CAD', 'AUD', 'SGD', 'JPY', 'CNY'];

    /**
     * Rules for creating a deal. "Lost reason is required for a lost stage" depends on the stage
     * (and, on update, on the stored deal) so it lives in RequiresLostReason instead.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],

            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],

            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'currency' => ['nullable', 'string', Rule::in(self::CURRENCIES)],
            'probability' => ['nullable', 'integer', 'between:0,100'],

            'expected_close_date' => ['nullable', 'date'],
            'actual_close_date' => ['nullable', 'date', 'before_or_equal:today'],

            'deal_stage_id' => ['required', 'integer', 'exists:deal_stages,id'],
            'deal_type_id' => ['nullable', 'integer', 'exists:deal_types,id'],
            'lead_source_id' => ['nullable', 'integer', 'exists:contact_sources,id'],
            'priority' => ['nullable', Rule::enum(DealPriority::class)],

            'next_step' => ['nullable', 'string', 'max:255'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
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
