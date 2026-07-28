<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class PersonalDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'nic' => strtoupper(trim((string) $this->input('nic'))),
            'address' => trim((string) $this->input('address')),
            'profile_photo_uuid' => filled($this->input('profile_photo_uuid'))
                ? strtoupper(trim((string) $this->input('profile_photo_uuid')))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'nic' => ['required', 'string', 'regex:/^([0-9]{9}[VX]|[0-9]{12})$/'],
            'address' => ['required', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'profile_photo_uuid' => ['nullable', 'string', 'size:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'nic.required' => 'NIC number is required.',
            'nic.regex' => 'Enter a valid NIC number.',
            'address.required' => 'Residential address is required.',
            'profile_photo.image' => 'Profile photo must be an image.',
            'profile_photo.mimes' => 'Profile photo must be a JPG, PNG, or WebP image.',
            'profile_photo.max' => 'Maximum profile photo size is 5MB.',
            'profile_photo_uuid.size' => 'Invalid profile photo reference.',
        ];
    }
}
