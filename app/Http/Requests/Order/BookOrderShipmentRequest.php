<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class BookOrderShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'courier_id' => ['required', 'integer', 'min:1'],
            // Optional: Create/Edit Assign Courier resolves the courier's default active service.
            'courier_service_id' => ['nullable', 'integer', 'min:1'],
            'courier_city_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
