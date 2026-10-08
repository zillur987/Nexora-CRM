<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies\Concerns;

use App\Http\Requests\Companies\Rules\CompanyRules;

/** Cleans user input before validation so the stored data is consistent. */
trait NormalizesCompanyInput
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (CompanyRules::TEXT_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('currency'))) {
            $merge['currency'] = strtoupper(trim($this->input('currency')));
        }

        foreach (CompanyRules::URL_FIELDS as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $merge[$field] = ($value !== '' && ! preg_match('#^https?://#i', $value)) ? "https://{$value}" : $value;
        }

        $this->merge($merge);
    }
}
