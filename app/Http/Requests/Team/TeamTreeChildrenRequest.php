<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class TeamTreeChildrenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
