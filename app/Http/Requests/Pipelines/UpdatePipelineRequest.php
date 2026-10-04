<?php

declare(strict_types=1);
namespace App\Http\Requests\Pipelines;
use Illuminate\Foundation\Http\FormRequest;
class UpdatePipelineRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name'=>['sometimes','required','string','max:120'],'description'=>['sometimes','nullable','string','max:1000'],'is_default'=>['sometimes','boolean'],'is_active'=>['sometimes','boolean']]; }
}
