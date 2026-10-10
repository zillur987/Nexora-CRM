<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Http\Requests\Deals\Concerns\NormalizesDealInput;
use App\Http\Requests\Deals\Concerns\RequiresLostReason;
use App\Http\Requests\Deals\Rules\DealRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDealRequest extends FormRequest
{
    use NormalizesDealInput;
    use RequiresLostReason;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return DealRules::attributes();
    }
}
