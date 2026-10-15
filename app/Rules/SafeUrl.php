<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Allows relative paths, #anchors, http(s), tel: and mailto: only. Blocks javascript:, data: and friends. */
final class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('~^(/(?!/)|#|https?://|tel:|mailto:)~i', trim($value))) {
            $fail('The :attribute must start with /, #, http(s)://, tel: or mailto:.');
        }
    }
}
