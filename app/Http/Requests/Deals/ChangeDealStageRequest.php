<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeDealStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['stage' => ['required', Rule::enum(DealStage::class)]];
    }

    public function stage(): DealStage
    {
        return DealStage::from($this->validated('stage'));
    }
}
