<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class CompanyDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'address' => trim((string) $this->input('address')),
            'customer_care_phone' => preg_replace('/\D+/', '', (string) $this->input('customer_care_phone')) ?: null,
            'registration_number' => filled($this->input('registration_number'))
                ? trim((string) $this->input('registration_number'))
                : null,
            'logo_uuid' => filled($this->input('logo_uuid'))
                ? strtoupper(trim((string) $this->input('logo_uuid')))
                : null,
            'business_reg_pdf_uuid' => filled($this->input('business_reg_pdf_uuid'))
                ? strtoupper(trim((string) $this->input('business_reg_pdf_uuid')))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string', 'max:500'],
            'customer_care_phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo_uuid' => ['nullable', 'string', 'size:10'],
            'business_reg_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'business_reg_pdf_uuid' => ['nullable', 'string', 'size:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'name.required' => 'Company name is required.',
            'address.required' => 'Company address is required.',
            'customer_care_phone.required' => 'Customer care number is required.',
            'customer_care_phone.regex' => 'Enter a valid 10 digit customer care number.',
            'logo.image' => 'Company logo must be an image.',
            'logo.mimes' => 'Company logo must be a JPG, PNG, or WebP image.',
            'logo.max' => 'Maximum company logo size is 5MB.',
            'logo_uuid.size' => 'Invalid company logo reference.',
            'business_reg_pdf.mimes' => 'Business registration document must be a PDF.',
            'business_reg_pdf.max' => 'Maximum business registration document size is 10MB.',
            'business_reg_pdf_uuid.size' => 'Invalid business registration document reference.',
        ];
    }
}
