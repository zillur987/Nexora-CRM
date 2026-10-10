<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals\Concerns;

use App\Http\Requests\Deals\Rules\DealRules;

/** Cleans user input before validation so the stored data is consistent. */
trait NormalizesDealInput
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (DealRules::TEXT_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('currency'))) {
            $merge['currency'] = mb_strtoupper(trim($this->input('currency')));
        }

        $this->merge($merge);
    }
}
