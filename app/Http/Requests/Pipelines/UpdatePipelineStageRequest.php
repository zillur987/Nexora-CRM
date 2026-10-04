<?php

declare(strict_types=1);
namespace App\Http\Requests\Pipelines;
use Illuminate\Foundation\Http\FormRequest;
class UpdatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name'=>['sometimes','required','string','max:80'],'position'=>['sometimes','integer','min:1'],'color'=>['sometimes','string','max:30'],'probability'=>['sometimes','integer','min:0','max:100'],'is_won'=>['sometimes','boolean'],'is_lost'=>['sometimes','boolean']]; }
}
