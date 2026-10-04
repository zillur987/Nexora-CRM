<?php

declare(strict_types=1);
namespace App\Http\Requests\Pipelines;
use Illuminate\Foundation\Http\FormRequest;
class ReorderPipelineStagesRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['stages'=>['required','array','min:1'],'stages.*.id'=>['required','uuid'],'stages.*.position'=>['required','integer','min:1']]; }
}
