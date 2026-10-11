<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Http\Requests\Quotes\Rules\QuoteRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // add your permission check here, as for the other modules
    }

    public function rules(): array
    {
        return QuoteRules::rules(partial: false);
    }
}
