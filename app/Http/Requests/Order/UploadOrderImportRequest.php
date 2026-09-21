<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrderImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'extensions:csv,xlsx',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV or XLSX file to import.',
            'file.extensions' => 'Only CSV and XLSX files are supported.',
            'file.max' => 'The import file must be 5 MB or smaller.',
        ];
    }
}