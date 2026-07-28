<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Enums\FileCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistrationUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_uuid' => strtoupper(trim((string) $this->input('user_uuid'))),
            'category' => strtoupper(trim((string) $this->input('category'))),
            'entity_type' => filled($this->input('entity_type'))
                ? strtoupper(trim((string) $this->input('entity_type')))
                : null,
            'entity_uuid' => filled($this->input('entity_uuid'))
                ? strtoupper(trim((string) $this->input('entity_uuid')))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'category' => [
                'required',
                'string',
                Rule::in([
                    FileCategory::COMPANY_LOGO->value,
                    FileCategory::BUSINESS_REGISTRATION->value,
                ]),
            ],
            'entity_type' => ['nullable', 'string', 'max:30'],
            'entity_uuid' => ['nullable', 'string', 'size:10'],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->hasFile('file')) {
                return;
            }

            $category = FileCategory::tryFrom((string) $this->input('category'));
            $file = $this->file('file');

            if ($category === null || $file === null) {
                return;
            }

            if ($category === FileCategory::COMPANY_LOGO) {
                if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $validator->errors()->add('file', 'File must be a JPG, PNG, or WebP image.');
                }

                if ($file->getSize() > (5120 * 1024)) {
                    $validator->errors()->add('file', 'Maximum file size is 5MB.');
                }

                return;
            }

            if ($category === FileCategory::BUSINESS_REGISTRATION) {
                if ($file->getMimeType() !== 'application/pdf'
                    && strtolower((string) $file->getClientOriginalExtension()) !== 'pdf'
                ) {
                    $validator->errors()->add('file', 'Business registration document must be a PDF.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'category.required' => 'File category is required.',
            'category.in' => 'Invalid file category.',
            'file.required' => 'File is required.',
            'file.max' => 'Maximum file size is 10MB.',
        ];
    }
}
