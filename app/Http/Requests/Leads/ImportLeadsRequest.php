<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class ImportLeadsRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'extensions:csv,txt',
                // Excel on Windows labels CSV files as application/vnd.ms-excel.
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file for uploading.',
            'file.max' => 'That file is too large (max 5 MB).',
            'file.extensions' => 'Please choose a .csv file.',
            'file.mimetypes' => 'Please choose a valid CSV file.',
        ];
    }
}
