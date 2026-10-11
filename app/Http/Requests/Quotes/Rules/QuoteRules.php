<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes\Rules;

use App\Enums\QuoteStatus;
use Illuminate\Validation\Rule;

/** One rule set for create and update, so they can never drift apart. */
final class QuoteRules
{
    /** Keep in sync with resources/js/utils/dealOptions.js (shared with Deals and Products). */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED', 'SAR', 'CAD', 'AUD', 'SGD', 'JPY', 'CNY'];

    /** @return array<string, list<mixed>> */
    public static function rules(bool $partial): array
    {
        // On update every top-level field is optional; when `items` is sent it must still be a valid, non-empty list.
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'subject' => [...$required, 'string', 'max:150'],
            'status' => ['sometimes', Rule::enum(QuoteStatus::class)],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'deal_id' => ['sometimes', 'nullable', 'integer', 'exists:deals,id'],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['sometimes', 'nullable', 'integer', 'exists:contacts,id'],
            'issue_date' => [...$required, 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => [...$required, 'string', Rule::in(self::CURRENCIES)],
            'terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'items' => [...$required, 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:180'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
