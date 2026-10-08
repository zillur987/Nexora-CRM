<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts\Concerns;

use App\Http\Requests\Contacts\Rules\ContactRules;

/** Cleans user input before validation so the stored data is consistent. */
trait NormalizesContactInput
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (ContactRules::TEXT_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }

        foreach (ContactRules::URL_FIELDS as $field) {
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
