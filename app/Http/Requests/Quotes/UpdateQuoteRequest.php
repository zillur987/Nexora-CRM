<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Http\Requests\Quotes\Rules\QuoteRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return QuoteRules::rules(partial: true);
    }
}
